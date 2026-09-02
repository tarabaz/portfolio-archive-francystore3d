<?php
/**
 * Registrazione del Custom Post Type "pezzo".
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT "pezzo": ogni post è un oggetto realizzato da FrancyStore3D
 * (action figure, lampada, diorama, stand...). È un portfolio, non un
 * catalogo e-commerce: niente prezzo, niente carrello.
 */
class FSP_CPT {

	/**
	 * Slug interno del post type.
	 *
	 * Prefissato: "pezzo" da solo è una parola comune e rischierebbe di
	 * collidere con un altro plugin sullo stesso sito. Non compare
	 * nell'URL pubblico, che usa invece lo slug di rewrite qui sotto.
	 */
	const POST_TYPE = 'fsp_pezzo';

	/**
	 * Slug pubblico di default usato nell'URL.
	 *
	 * NON è "archivio": su questo sito quello slug è già occupato dal
	 * plugin Devil Fruit Archive, e due CPT con la stessa rewrite si
	 * rubano le richieste a vicenda (vince quello registrato per ultimo,
	 * con risultati che cambiano da un caricamento all'altro). Il valore
	 * effettivo è comunque configurabile dalle impostazioni.
	 */
	const DEFAULT_SLUG = 'portfolio';

	/**
	 * Aggancia la registrazione del CPT e le regole di query pubblica.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'ensure_thumbnail_support' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'configure_archive_query' ) );
	}

	/**
	 * Slug pubblico effettivo, dalle impostazioni.
	 *
	 * @return string
	 */
	public static function get_slug() {
		return FSP_Settings::get_archive_slug();
	}

	/**
	 * L'archivio pubblico mostra TUTTI i pezzi in una pagina sola,
	 * ordinati dal più recente.
	 *
	 * Niente paginazione, ed è una scelta voluta: i filtri lavorano in
	 * JavaScript sulle schede già presenti nel DOM, quindi spezzare
	 * l'archivio in pagine significherebbe filtrare solo la pagina
	 * corrente — l'utente spunta "Lampade" e ne vede tre invece di
	 * dodici, senza capire perché. Con un centinaio di pezzi il costo
	 * di una pagina unica è trascurabile (le immagini sono in
	 * lazy-loading, quindi il browser scarica solo quelle a schermo).
	 *
	 * @param WP_Query $query Query in corso.
	 */
	public static function configure_archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		/*
		 * Vale anche per gli archivi di sezione e tag: usano lo stesso
		 * template della griglia, quindi devono comportarsi allo stesso
		 * modo. Ci si arriva da un link diretto, dato che i termini nelle
		 * schede rimandano invece alla griglia completa con il filtro in
		 * querystring.
		 */
		$is_portfolio_archive = $query->is_post_type_archive( self::POST_TYPE )
			|| $query->is_tax( array( FSP_Taxonomies::SECTION, FSP_Taxonomies::TAG ) );

		if ( ! $is_portfolio_archive ) {
			return;
		}

		$query->set( 'posts_per_page', -1 );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}

	/**
	 * Il box "Immagine in evidenza" compare in wp-admin solo se il TEMA
	 * attivo dichiara add_theme_support('post-thumbnails'): il
	 * 'supports' => array('thumbnail') del post type da solo non basta.
	 * Il plugin deve funzionare con qualsiasi tema, quindi il supporto
	 * lo forziamo qui, limitandolo al solo CPT del portfolio.
	 */
	public static function ensure_thumbnail_support() {
		add_theme_support( 'post-thumbnails', array( self::POST_TYPE ) );
	}

	/**
	 * Registra il post type "pezzo".
	 *
	 * Richiamato sia sull'hook "init" sia direttamente in fase di
	 * attivazione del plugin, prima del flush delle rewrite rules.
	 */
	public static function register_post_type() {
		$slug = self::get_slug();

		$labels = array(
			'name'                  => __( 'Portfolio', 'francystore-portfolio' ),
			'singular_name'         => __( 'Pezzo', 'francystore-portfolio' ),
			'menu_name'             => __( 'Portfolio', 'francystore-portfolio' ),
			'name_admin_bar'        => __( 'Pezzo', 'francystore-portfolio' ),
			'add_new'               => __( 'Aggiungi pezzo', 'francystore-portfolio' ),
			'add_new_item'          => __( 'Aggiungi nuovo pezzo', 'francystore-portfolio' ),
			'edit_item'             => __( 'Modifica pezzo', 'francystore-portfolio' ),
			'new_item'              => __( 'Nuovo pezzo', 'francystore-portfolio' ),
			'view_item'             => __( 'Visualizza pezzo', 'francystore-portfolio' ),
			'view_items'            => __( 'Visualizza portfolio', 'francystore-portfolio' ),
			'search_items'          => __( 'Cerca pezzi', 'francystore-portfolio' ),
			'not_found'             => __( 'Nessun pezzo trovato', 'francystore-portfolio' ),
			'not_found_in_trash'    => __( 'Nessun pezzo nel cestino', 'francystore-portfolio' ),
			'all_items'             => __( 'Tutti i pezzi', 'francystore-portfolio' ),
			'archives'              => __( 'Archivio portfolio', 'francystore-portfolio' ),
			'featured_image'        => __( 'Immagine di copertina', 'francystore-portfolio' ),
			'set_featured_image'    => __( 'Imposta copertina', 'francystore-portfolio' ),
			'remove_featured_image' => __( 'Rimuovi copertina', 'francystore-portfolio' ),
			'use_featured_image'    => __( 'Usa come copertina', 'francystore-portfolio' ),
		);

		$args = array(
			'labels'             => $labels,
			'description'        => __( 'Pezzi realizzati e catalogati nel portfolio FrancyStore3D.', 'francystore-portfolio' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => true,
			/*
			 * REST disattivata di proposito: con show_in_rest attivo la
			 * schermata di modifica è quella a blocchi, che qui non serve
			 * — il pezzo si compila per campi, non si impagina. Senza,
			 * WordPress usa l'editor classico e i meta box compaiono
			 * subito sotto il titolo invece che schiacciati in un
			 * pannello laterale.
			 */
			'show_in_rest'       => false,
			'menu_icon'          => 'dashicons-format-gallery',
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => $slug,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'hierarchical'       => false,
			/*
			 * Niente "editor": la descrizione si scrive nel campo del
			 * meta box, che salva comunque nel contenuto del post. Il
			 * pezzo è una scheda da compilare, non una pagina da
			 * impaginare, e un editor completo inviterebbe solo a
			 * costruire layout che il template poi non rispetta.
			 *
			 * Resta "thumbnail" perché l'immagine principale è la
			 * featured image del post: il meta box la imposta con un
			 * campo proprio, ma sotto resta il meccanismo standard di
			 * WordPress.
			 */
			'supports'           => array( 'title', 'thumbnail', 'page-attributes' ),
			'menu_position'      => 21,
		);

		register_post_type( self::POST_TYPE, $args );
	}
}
