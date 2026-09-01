<?php
/**
 * Meta box di compilazione del pezzo in wp-admin.
 *
 * Un solo box "Dati del pezzo" con i campi base, la tabella degli
 * attributi liberi e la galleria: tenerli separati in tre box
 * costringerebbe a scorrere avanti e indietro durante l'inserimento,
 * che è l'operazione che si ripete più spesso.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Metabox {

	/** Azione del nonce di salvataggio. */
	const NONCE_ACTION = 'fsp_save_piece_meta';

	/** Nome del campo nonce nel form. */
	const NONCE_NAME = 'fsp_piece_meta_nonce';

	/**
	 * Aggancia registrazione, salvataggio e assets del meta box.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_' . FSP_CPT::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	/**
	 * Registra il meta box sulla schermata di modifica del pezzo.
	 */
	public static function register_meta_box() {
		add_meta_box(
			'fsp-piece-data',
			__( 'Dati del pezzo', 'francystore-portfolio' ),
			array( __CLASS__, 'render' ),
			FSP_CPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Carica CSS/JS del meta box nelle sole schermate di modifica del
	 * CPT portfolio.
	 *
	 * @param string $hook Hook della pagina admin corrente.
	 */
	public static function enqueue_editor_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		if ( get_post_type() !== FSP_CPT::POST_TYPE ) {
			return;
		}

		self::enqueue_media_picker();
	}

	/**
	 * Carica wp.media più CSS e JS condivisi da meta box, pagina
	 * impostazioni e schermate delle sezioni.
	 *
	 * Sta qui e non in ogni chiamante perché le tre schermate usano lo
	 * stesso identico markup del selettore immagine: un'unica coppia
	 * CSS/JS evita che si disallineino nel tempo.
	 */
	public static function enqueue_media_picker() {
		wp_enqueue_media();

		wp_enqueue_style(
			'fsp-admin',
			FSP_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			FSP_VERSION
		);

		wp_enqueue_script(
			'fsp-admin',
			FSP_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			FSP_VERSION,
			true
		);

		wp_localize_script(
			'fsp-admin',
			'fspAdmin',
			array(
				'mediaTitle'        => __( 'Seleziona un\'immagine', 'francystore-portfolio' ),
				'mediaButton'       => __( 'Usa questa immagine', 'francystore-portfolio' ),
				'galleryTitle'      => __( 'Seleziona le immagini della galleria', 'francystore-portfolio' ),
				'galleryButton'     => __( 'Usa queste immagini', 'francystore-portfolio' ),
				'removeLabel'       => __( 'Rimuovi', 'francystore-portfolio' ),
				'attributeLabel'    => __( 'Dato', 'francystore-portfolio' ),
				'attributeValue'    => __( 'Valore', 'francystore-portfolio' ),
				'confirmRemoveRow'  => __( 'Vuoi rimuovere questa riga?', 'francystore-portfolio' ),
			)
		);
	}

	/**
	 * Stampa il contenuto del meta box.
	 *
	 * @param WP_Post $post Pezzo in modifica.
	 */
	public static function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$attributes  = FSP_Meta::get_attributes( $post->ID );
		$gallery     = FSP_Meta::get_gallery( $post->ID );
		$suggestions = FSP_Settings::get_attribute_suggestions();
		?>
		<div class="fsp-box">

			<h3 class="fsp-box__title"><?php esc_html_e( 'Dati base', 'francystore-portfolio' ); ?></h3>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Compila solo quello che ha senso per questo pezzo: i campi lasciati vuoti non compaiono nella scheda pubblica.', 'francystore-portfolio' ); ?>
			</p>

			<div class="fsp-grid">
				<?php foreach ( FSP_Meta::get_base_fields() as $key => $label ) : ?>
					<?php $field_id = 'fsp-field-' . $key; ?>
					<p class="fsp-grid__cell">
						<label class="fsp-label" for="<?php echo esc_attr( $field_id ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
						<input type="text"
							class="widefat"
							id="<?php echo esc_attr( $field_id ); ?>"
							name="<?php echo esc_attr( FSP_Meta::PREFIX . $key ); ?>"
							value="<?php echo esc_attr( FSP_Meta::get( $post->ID, $key ) ); ?>"
							<?php if ( 'codice' === $key ) : ?>
								placeholder="<?php esc_attr_e( 'es. FS-042', 'francystore-portfolio' ); ?>"
							<?php endif; ?>>
					</p>
				<?php endforeach; ?>
			</div>

			<p class="fsp-box__hint">
				<?php esc_html_e( 'Il codice pezzo compare nella scheda pubblica e viene copiato negli appunti dal pulsante di contatto Instagram: serve a farti capire al volo di quale oggetto ti stanno scrivendo.', 'francystore-portfolio' ); ?>
			</p>

			<hr class="fsp-box__sep">

			<h3 class="fsp-box__title"><?php esc_html_e( 'Altri dati', 'francystore-portfolio' ); ?></h3>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Righe libere per tutto ciò che vale solo su questo pezzo: alimentazione e tipo di illuminazione per una lampada, scala per una figure, e così via. Le etichette che scrivi spesso te le suggerisce il campo mentre digiti.', 'francystore-portfolio' ); ?>
			</p>

			<?php // Le chiavi suggerite si impostano da Portfolio > Impostazioni. ?>
			<datalist id="fsp-attribute-suggestions">
				<?php foreach ( $suggestions as $suggestion ) : ?>
					<option value="<?php echo esc_attr( $suggestion ); ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<table class="fsp-attributes widefat" data-fsp-attributes>
				<thead>
					<tr>
						<th class="fsp-attributes__handle" scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Ordina', 'francystore-portfolio' ); ?></span></th>
						<th scope="col"><?php esc_html_e( 'Dato', 'francystore-portfolio' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Valore', 'francystore-portfolio' ); ?></th>
						<th class="fsp-attributes__actions" scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Azioni', 'francystore-portfolio' ); ?></span></th>
					</tr>
				</thead>
				<tbody data-fsp-attributes-body>
					<?php foreach ( $attributes as $index => $row ) : ?>
						<?php
						self::render_attribute_row(
							(int) $index,
							(string) $row['label'],
							(string) $row['value']
						);
						?>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<button type="button" class="button" data-fsp-attributes-add>
					<?php esc_html_e( '+ Aggiungi riga', 'francystore-portfolio' ); ?>
				</button>
			</p>

			<?php
			/*
			 * Riga modello per il JS. È dentro un <template>, quindi il
			 * browser non la considera parte del form: senza, i suoi
			 * campi verrebbero inviati al salvataggio come una riga
			 * vuota in più ad ogni aggiornamento del pezzo.
			 */
			?>
			<template data-fsp-attributes-template>
				<?php self::render_attribute_row( 0, '', '', true ); ?>
			</template>

			<hr class="fsp-box__sep">

			<h3 class="fsp-box__title"><?php esc_html_e( 'Galleria', 'francystore-portfolio' ); ?></h3>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Gli scatti aggiuntivi del pezzo, mostrati sotto l\'immagine principale nella scheda. La copertina si imposta invece dal box "Immagine di copertina" nella colonna a destra: è quella che compare nella griglia del portfolio.', 'francystore-portfolio' ); ?>
			</p>

			<div class="fsp-gallery" data-fsp-gallery>
				<ul class="fsp-gallery__list" data-fsp-gallery-list>
					<?php foreach ( $gallery as $image_id ) : ?>
						<?php self::render_gallery_item( (int) $image_id ); ?>
					<?php endforeach; ?>
				</ul>

				<input type="hidden"
					name="<?php echo esc_attr( FSP_Meta::KEY_GALLERY ); ?>"
					value="<?php echo esc_attr( implode( ',', $gallery ) ); ?>"
					data-fsp-gallery-input>

				<p>
					<button type="button" class="button" data-fsp-gallery-select>
						<?php esc_html_e( 'Gestisci galleria', 'francystore-portfolio' ); ?>
					</button>
				</p>
			</div>

		</div>
		<?php
	}

	/**
	 * Stampa una riga della tabella attributi.
	 *
	 * @param int    $index    Indice della riga nell'array inviato dal form.
	 * @param string $label    Etichetta del dato.
	 * @param string $value    Valore del dato.
	 * @param bool   $is_model True se è la riga modello dentro <template>.
	 */
	private static function render_attribute_row( $index, $label, $value, $is_model = false ) {
		// Nel modello l'indice è un segnaposto: il JS lo sostituisce con
		// la posizione reale al momento dell'inserimento.
		$key = $is_model ? '__INDEX__' : (string) $index;
		?>
		<tr class="fsp-attributes__row" data-fsp-attributes-row>
			<td class="fsp-attributes__handle">
				<span class="fsp-attributes__grip dashicons dashicons-menu" aria-hidden="true"></span>
			</td>
			<td>
				<input type="text"
					class="widefat"
					list="fsp-attribute-suggestions"
					name="<?php echo esc_attr( FSP_Meta::KEY_ATTRIBUTES . '[' . $key . '][label]' ); ?>"
					value="<?php echo esc_attr( $label ); ?>"
					placeholder="<?php esc_attr_e( 'es. Alimentazione', 'francystore-portfolio' ); ?>">
			</td>
			<td>
				<input type="text"
					class="widefat"
					name="<?php echo esc_attr( FSP_Meta::KEY_ATTRIBUTES . '[' . $key . '][value]' ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'es. USB-C 5V', 'francystore-portfolio' ); ?>">
			</td>
			<td class="fsp-attributes__actions">
				<button type="button" class="button-link fsp-attributes__remove" data-fsp-attributes-remove>
					<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Rimuovi riga', 'francystore-portfolio' ); ?></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Stampa una miniatura della galleria.
	 *
	 * @param int $image_id ID dell'allegato.
	 */
	private static function render_gallery_item( $image_id ) {
		$thumb = wp_get_attachment_image( $image_id, 'thumbnail' );

		// Un allegato cancellato dalla Libreria Media resta nell'elenco
		// salvato: senza questo controllo la galleria mostrerebbe un
		// riquadro vuoto senza spiegazione.
		if ( ! $thumb ) {
			return;
		}
		?>
		<li class="fsp-gallery__item" data-fsp-gallery-item data-id="<?php echo esc_attr( (string) $image_id ); ?>">
			<?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() restituisce markup già sicuro. ?>
		</li>
		<?php
	}

	/**
	 * Salva i campi del meta box.
	 *
	 * @param int     $post_id ID del pezzo.
	 * @param WP_Post $post    Oggetto del pezzo.
	 */
	public static function save( $post_id, $post ) {
		// Il salvataggio automatico invia un post parziale: scriverlo
		// azzererebbe i campi non inclusi.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array_keys( FSP_Meta::get_base_fields() ) as $key ) {
			$meta_key = FSP_Meta::PREFIX . $key;
			$value    = isset( $_POST[ $meta_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) : '';

			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}

		/*
		 * Gli attributi arrivano come array indicizzato dai nomi dei
		 * campi. wp_unslash() prima della sanitizzazione perché
		 * WordPress aggiunge slash a tutto $_POST, e senza toglierli un
		 * valore con l'apostrofo (frequente in italiano) si salverebbe
		 * con la barra rovescia davanti.
		 */
		$raw_attributes = isset( $_POST[ FSP_Meta::KEY_ATTRIBUTES ] ) ? wp_unslash( $_POST[ FSP_Meta::KEY_ATTRIBUTES ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato da sanitize_attributes().
		$attributes     = FSP_Meta::sanitize_attributes( $raw_attributes );

		if ( $attributes ) {
			update_post_meta( $post_id, FSP_Meta::KEY_ATTRIBUTES, $attributes );
		} else {
			delete_post_meta( $post_id, FSP_Meta::KEY_ATTRIBUTES );
		}

		$raw_gallery = isset( $_POST[ FSP_Meta::KEY_GALLERY ] ) ? sanitize_text_field( wp_unslash( $_POST[ FSP_Meta::KEY_GALLERY ] ) ) : '';
		$gallery     = FSP_Meta::sanitize_gallery( $raw_gallery );

		if ( $gallery ) {
			update_post_meta( $post_id, FSP_Meta::KEY_GALLERY, $gallery );
		} else {
			delete_post_meta( $post_id, FSP_Meta::KEY_GALLERY );
		}
	}
}
