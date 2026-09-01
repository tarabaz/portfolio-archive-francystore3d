<?php
/**
 * Serve i template di frontend come documenti HTML completi,
 * indipendenti dal tema attivo.
 *
 * Il portfolio non passa da header.php/footer.php del tema: così il
 * layout resta identico qualunque tema sia installato, e cambiare tema
 * domani non sposta un pixel. Si continuano però a chiamare wp_head()
 * e wp_footer() nei template, perché è lì che si agganciano pixel
 * Meta, analytics e banner dei cookie: saltarli significherebbe
 * perdere il tracciamento proprio sulle pagine che si promuovono.
 * Degli stili del tema che arrivano da quegli hook ci si libera con la
 * rimozione mirata più sotto.
 *
 * Il tema attivo può comunque sovrascrivere i template copiandoli in
 * wp-content/themes/<tema>/francystore-portfolio/.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Template_Loader {

	/** Sottocartella nel tema usata per gli override. */
	const THEME_SUBDIR = 'francystore-portfolio';

	/**
	 * Aggancia sostituzione dei template e caricamento degli assets.
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );

		// Priorità alta: deve girare dopo che il tema ha registrato i propri stili.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'dequeue_theme_styles' ), 100 );
	}

	/**
	 * Sostituisce il template del tema con quello del plugin sulle
	 * pagine del portfolio.
	 *
	 * @param string $template Percorso risolto da WordPress.
	 * @return string
	 */
	public static function template_include( $template ) {
		if ( is_singular( FSP_CPT::POST_TYPE ) ) {
			return self::locate( 'single-pezzo.php', $template );
		}

		if ( self::is_portfolio_archive() ) {
			return self::locate( 'archive-portfolio.php', $template );
		}

		return $template;
	}

	/**
	 * True sull'archivio del portfolio e sugli archivi delle sue
	 * tassonomie.
	 *
	 * Gli archivi di sezione e tag usano lo stesso template della
	 * griglia: ci si arriva dai link "sezione" nelle schede, e trovarci
	 * il layout del tema al posto del portfolio sarebbe uno stacco
	 * netto in mezzo alla navigazione.
	 *
	 * @return bool
	 */
	public static function is_portfolio_archive() {
		return is_post_type_archive( FSP_CPT::POST_TYPE )
			|| is_tax( array( FSP_Taxonomies::SECTION, FSP_Taxonomies::TAG ) );
	}

	/**
	 * Cerca prima un override nel tema (child o parent), poi ricade sul
	 * template del plugin.
	 *
	 * @param string $file_name Nome del file di template.
	 * @param string $fallback  Template originale, se non si trova nulla.
	 * @return string
	 */
	private static function locate( $file_name, $fallback ) {
		$theme_file = locate_template( array( self::THEME_SUBDIR . '/' . $file_name ) );

		if ( $theme_file ) {
			return $theme_file;
		}

		$plugin_file = FSP_PLUGIN_DIR . 'templates/' . $file_name;

		return file_exists( $plugin_file ) ? $plugin_file : $fallback;
	}

	/**
	 * True se la pagina corrente è una di quelle servite dal plugin.
	 *
	 * @return bool
	 */
	private static function is_portfolio_page() {
		return is_singular( FSP_CPT::POST_TYPE ) || self::is_portfolio_archive();
	}

	/**
	 * Carica CSS e JS del portfolio solo dove servono.
	 */
	public static function enqueue_frontend_assets() {
		if ( ! self::is_portfolio_page() ) {
			return;
		}

		wp_enqueue_style(
			'fsp-fonts',
			'https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@400;500;600;700&family=IBM+Plex+Mono:ital,wght@0,300;0,400;0,500;1,300&display=swap',
			array(),
			null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- la versione la gestisce Google nell'URL.
		);

		wp_enqueue_style(
			'fsp-frontend',
			FSP_PLUGIN_URL . 'assets/css/portfolio.css',
			array( 'fsp-fonts' ),
			FSP_VERSION
		);

		wp_enqueue_script(
			'fsp-frontend',
			FSP_PLUGIN_URL . 'assets/js/portfolio.js',
			array(),
			FSP_VERSION,
			true
		);

		wp_localize_script(
			'fsp-frontend',
			'fspL10n',
			array(
				'copied'      => __( 'Codice copiato', 'francystore-portfolio' ),
				'copyFailed'  => __( 'Copia non riuscita', 'francystore-portfolio' ),
				/* translators: %d: numero di pezzi mostrati. */
				'countOne'    => __( '%d pezzo', 'francystore-portfolio' ),
				/* translators: %d: numero di pezzi mostrati. */
				'countMany'   => __( '%d pezzi', 'francystore-portfolio' ),
			)
		);
	}

	/**
	 * Toglie i fogli di stile del tema dalle pagine del portfolio.
	 *
	 * Servono a poco qui (il markup è tutto nostro) e possono fare
	 * danni: i reset dei temi impongono per esempio "img { max-width:
	 * 100% }", che rimetterebbe in scala gli sfondi a dimensione
	 * naturale. Si tolgono solo gli stili il cui handle corrisponde al
	 * tema attivo, lasciando intatto tutto il resto — quindi anche gli
	 * script di analytics e cookie banner, che vanno preservati.
	 */
	public static function dequeue_theme_styles() {
		if ( ! self::is_portfolio_page() ) {
			return;
		}

		$theme  = get_stylesheet();
		$parent = get_template();

		// Handle usati più di frequente dai temi per il proprio CSS.
		$handles = array( $theme, $parent, $theme . '-style', $parent . '-style', 'theme-style', 'style' );

		foreach ( array_unique( $handles ) as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				wp_dequeue_style( $handle );
			}
		}
	}
}
