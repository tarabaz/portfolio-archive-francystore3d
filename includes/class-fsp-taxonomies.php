<?php
/**
 * Tassonomie del portfolio: Sezioni e Tipologie.
 *
 * Sono tassonomie vere e non un elenco di stringhe salvato nelle
 * impostazioni: così si creano, rinominano, riordinano e cancellano
 * dalle schermate standard di WordPress, con slug, descrizioni,
 * conteggi e assegnazione rapida in fase di scrittura già pronti.
 * Reimplementare tutto questo dentro una pagina di opzioni
 * significherebbe rifare peggio quello che il core fa già bene.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Taxonomies {

	/** Tassonomia gerarchica: la macro-categoria del pezzo. */
	const SECTION = 'fsp_sezione';

	/**
	 * Tipologia del pezzo: gerarchica, quindi in fase di scrittura si
	 * sceglie a spunte da un elenco chiuso invece di digitare a mano.
	 *
	 * Lo slug interno resta "fsp_tag" anche se ovunque si legge
	 * "Tipologia": è la chiave con cui i termini sono già associati ai
	 * pezzi nel database, e cambiarla li staccherebbe tutti. Il nome
	 * interno non compare da nessuna parte nell'interfaccia né negli
	 * indirizzi pubblici.
	 */
	const TAG = 'fsp_tag';

	/** Chiave del term meta con l'ID dell'immagine di sfondo della sezione. */
	const META_BACKGROUND = 'fsp_background_image';

	/**
	 * Registra tassonomie, campi immagine di sfondo e assets admin.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );

		// Campo "immagine di sfondo" nelle schermate di creazione e modifica sezione.
		add_action( self::SECTION . '_add_form_fields', array( __CLASS__, 'render_add_background_field' ) );
		add_action( self::SECTION . '_edit_form_fields', array( __CLASS__, 'render_edit_background_field' ) );
		add_action( 'created_' . self::SECTION, array( __CLASS__, 'save_background_field' ) );
		add_action( 'edited_' . self::SECTION, array( __CLASS__, 'save_background_field' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Registra le due tassonomie del CPT portfolio.
	 */
	public static function register_taxonomies() {
		register_taxonomy(
			self::SECTION,
			FSP_CPT::POST_TYPE,
			array(
				'labels'            => array(
					'name'              => __( 'Sezioni', 'francystore-portfolio' ),
					'singular_name'     => __( 'Sezione', 'francystore-portfolio' ),
					'menu_name'         => __( 'Sezioni', 'francystore-portfolio' ),
					'all_items'         => __( 'Tutte le sezioni', 'francystore-portfolio' ),
					'edit_item'         => __( 'Modifica sezione', 'francystore-portfolio' ),
					'update_item'       => __( 'Aggiorna sezione', 'francystore-portfolio' ),
					'add_new_item'      => __( 'Aggiungi nuova sezione', 'francystore-portfolio' ),
					'new_item_name'     => __( 'Nome della nuova sezione', 'francystore-portfolio' ),
					'search_items'      => __( 'Cerca sezioni', 'francystore-portfolio' ),
					'parent_item'       => __( 'Sezione superiore', 'francystore-portfolio' ),
					'parent_item_colon' => __( 'Sezione superiore:', 'francystore-portfolio' ),
					'not_found'         => __( 'Nessuna sezione trovata', 'francystore-portfolio' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => 'portfolio-sezione',
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			self::TAG,
			FSP_CPT::POST_TYPE,
			array(
				'labels'            => array(
					'name'              => __( 'Tipologie', 'francystore-portfolio' ),
					'singular_name'     => __( 'Tipologia', 'francystore-portfolio' ),
					'menu_name'         => __( 'Tipologie', 'francystore-portfolio' ),
					'all_items'         => __( 'Tutte le tipologie', 'francystore-portfolio' ),
					'edit_item'         => __( 'Modifica tipologia', 'francystore-portfolio' ),
					'update_item'       => __( 'Aggiorna tipologia', 'francystore-portfolio' ),
					'add_new_item'      => __( 'Aggiungi nuova tipologia', 'francystore-portfolio' ),
					'new_item_name'     => __( 'Nome della nuova tipologia', 'francystore-portfolio' ),
					'search_items'      => __( 'Cerca tipologie', 'francystore-portfolio' ),
					'parent_item'       => __( 'Tipologia superiore', 'francystore-portfolio' ),
					'parent_item_colon' => __( 'Tipologia superiore:', 'francystore-portfolio' ),
					'not_found'         => __( 'Nessuna tipologia trovata', 'francystore-portfolio' ),
				),
				'public'            => true,
				/*
				 * Gerarchica non per fare sottocategorie, ma per il tipo di
				 * campo che ne consegue: WordPress mostra le tassonomie
				 * gerarchiche come elenco di spunte e quelle piatte come
				 * casella di testo libera. A spunte non serve ricordarsi
				 * come si era scritta un'etichetta la volta prima, e
				 * l'archivio non si riempie di doppioni tipo
				 * "anime" / "Anime" / "anime ".
				 */
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => false,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => 'portfolio-tipologia',
					'with_front' => false,
				),
			)
		);

		/*
		 * Il term meta va registrato esplicitamente per essere esposto
		 * in REST e per avere un sanitize applicato in automatico anche
		 * quando il valore arriva da fuori dalla nostra form.
		 */
		register_term_meta(
			self::SECTION,
			self::META_BACKGROUND,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
			)
		);
	}

	/**
	 * ID dell'immagine di sfondo associata a una sezione.
	 *
	 * @param int $term_id ID del termine.
	 * @return int 0 se non impostata.
	 */
	public static function get_background_id( $term_id ) {
		return (int) get_term_meta( $term_id, self::META_BACKGROUND, true );
	}

	/**
	 * Carica wp.media e lo script del selettore immagine nelle sole
	 * schermate delle tassonomie del portfolio.
	 *
	 * @param string $hook Hook della pagina admin corrente.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sola lettura, serve a decidere se caricare gli asset.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';

		if ( self::SECTION !== $taxonomy ) {
			return;
		}

		FSP_Metabox::enqueue_media_picker();
	}

	/**
	 * Campo immagine nella schermata "Aggiungi nuova sezione".
	 *
	 * In creazione il termine non esiste ancora, quindi non c'è nulla
	 * da precaricare: il markup è lo stesso della modifica ma con
	 * valore vuoto.
	 */
	public static function render_add_background_field() {
		wp_nonce_field( 'fsp_save_term_background', 'fsp_term_background_nonce' );
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Immagine di sfondo della sezione', 'francystore-portfolio' ); ?></label>
			<?php self::render_media_picker( 0 ); ?>
			<p class="description">
				<?php esc_html_e( 'Compare dietro alla griglia del portfolio quando questa sezione è l\'unica selezionata nei filtri. Se non la imposti resta lo sfondo generale del portfolio.', 'francystore-portfolio' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Campo immagine nella schermata "Modifica sezione".
	 *
	 * @param WP_Term $term Termine in modifica.
	 */
	public static function render_edit_background_field( $term ) {
		wp_nonce_field( 'fsp_save_term_background', 'fsp_term_background_nonce' );
		?>
		<tr class="form-field">
			<th scope="row">
				<label><?php esc_html_e( 'Immagine di sfondo della sezione', 'francystore-portfolio' ); ?></label>
			</th>
			<td>
				<?php self::render_media_picker( self::get_background_id( $term->term_id ) ); ?>
				<p class="description">
					<?php esc_html_e( 'Compare dietro alla griglia del portfolio quando questa sezione è l\'unica selezionata nei filtri. Se non la imposti resta lo sfondo generale del portfolio.', 'francystore-portfolio' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Markup del selettore immagine, condiviso fra creazione e modifica.
	 *
	 * @param int $image_id ID dell'immagine già selezionata (0 = nessuna).
	 */
	private static function render_media_picker( $image_id ) {
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="fsp-media" data-fsp-media>
			<div class="fsp-media__preview" data-fsp-media-preview>
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="">
				<?php endif; ?>
			</div>
			<input type="hidden"
				name="<?php echo esc_attr( self::META_BACKGROUND ); ?>"
				value="<?php echo esc_attr( (string) $image_id ); ?>"
				data-fsp-media-input>
			<button type="button" class="button" data-fsp-media-select>
				<?php esc_html_e( 'Scegli immagine', 'francystore-portfolio' ); ?>
			</button>
			<button type="button" class="button-link fsp-media__remove" data-fsp-media-remove<?php echo $image_id ? '' : ' hidden'; ?>>
				<?php esc_html_e( 'Rimuovi', 'francystore-portfolio' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Salva l'immagine di sfondo del termine.
	 *
	 * @param int $term_id ID del termine salvato.
	 */
	public static function save_background_field( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$nonce = isset( $_POST['fsp_term_background_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['fsp_term_background_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'fsp_save_term_background' ) ) {
			return;
		}

		/*
		 * Il campo può mancare del tutto (salvataggio rapido inline
		 * dalla lista termini, che non include la nostra form): in quel
		 * caso non si tocca il valore esistente, altrimenti un rename
		 * al volo cancellerebbe lo sfondo.
		 */
		if ( ! isset( $_POST[ self::META_BACKGROUND ] ) ) {
			return;
		}

		$image_id = absint( wp_unslash( $_POST[ self::META_BACKGROUND ] ) );

		if ( $image_id ) {
			update_term_meta( $term_id, self::META_BACKGROUND, $image_id );
		} else {
			delete_term_meta( $term_id, self::META_BACKGROUND );
		}
	}
}
