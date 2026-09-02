<?php
/**
 * Meta box di compilazione del pezzo in wp-admin.
 *
 * Un solo box con tutto quello che serve a descrivere un pezzo:
 * descrizione, immagini, dati tecnici e sfondo. Tenerli separati in più
 * box costringerebbe a scorrere avanti e indietro durante
 * l'inserimento, che è l'operazione che si ripete più spesso.
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

	/** Nome del campo della descrizione nel form. */
	const FIELD_DESCRIPTION = 'fsp_descrizione';

	/** Nome del campo dell'immagine principale nel form. */
	const FIELD_MAIN_IMAGE = 'fsp_immagine_principale';

	/**
	 * Aggancia registrazione, salvataggio e assets del meta box.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
		add_action( 'save_post_' . FSP_CPT::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'inject_description' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'print_notices' ) );
	}

	/**
	 * Registra il meta box sulla schermata di modifica del pezzo.
	 */
	public static function register_meta_box() {
		add_meta_box(
			'fsp-piece-data',
			__( 'Scheda del pezzo', 'francystore-portfolio' ),
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
				'mediaTitle'       => __( 'Seleziona un\'immagine', 'francystore-portfolio' ),
				'mediaButton'      => __( 'Usa questa immagine', 'francystore-portfolio' ),
				'galleryTitle'     => __( 'Seleziona le immagini della galleria', 'francystore-portfolio' ),
				'galleryButton'    => __( 'Usa queste immagini', 'francystore-portfolio' ),
				'removeLabel'      => __( 'Rimuovi', 'francystore-portfolio' ),
				'confirmRemoveRow' => __( 'Vuoi rimuovere questa riga?', 'francystore-portfolio' ),
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
		$main_image  = (int) get_post_thumbnail_id( $post->ID );
		$background  = (int) get_post_meta( $post->ID, FSP_Meta::KEY_BACKGROUND, true );
		?>
		<div class="fsp-box">

			<h3 class="fsp-box__title"><?php esc_html_e( 'Descrizione', 'francystore-portfolio' ); ?></h3>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Il testo che compare sotto le immagini, nel riquadro "Note di lavorazione". Vai a capo dove serve: gli a capo vengono rispettati.', 'francystore-portfolio' ); ?>
			</p>

			<?php
			/*
			 * Il campo scrive nel contenuto del post, non in un meta a
			 * parte: così le descrizioni già scritte quando il pezzo aveva
			 * ancora l'editor restano al loro posto e modificabili, e il
			 * template continua a leggerle da dove le ha sempre lette.
			 */
			?>
			<textarea class="widefat"
				rows="9"
				id="<?php echo esc_attr( self::FIELD_DESCRIPTION ); ?>"
				name="<?php echo esc_attr( self::FIELD_DESCRIPTION ); ?>"><?php echo esc_textarea( $post->post_content ); ?></textarea>

			<hr class="fsp-box__sep">

			<h3 class="fsp-box__title"><?php esc_html_e( 'Immagini', 'francystore-portfolio' ); ?></h3>

			<div class="fsp-images">
				<div class="fsp-images__main">
					<label class="fsp-label"><?php esc_html_e( 'Immagine principale', 'francystore-portfolio' ); ?></label>
					<?php self::render_media_picker( self::FIELD_MAIN_IMAGE, $main_image ); ?>
					<p class="fsp-box__hint">
						<?php esc_html_e( 'È la foto che rappresenta il pezzo nella griglia del portfolio, ed è anche quella grande in cima alla sua scheda. Se non la imposti, la griglia mostra un riquadro vuoto.', 'francystore-portfolio' ); ?>
					</p>
				</div>

				<div class="fsp-images__gallery">
					<label class="fsp-label"><?php esc_html_e( 'Altre immagini', 'francystore-portfolio' ); ?></label>

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
								<?php esc_html_e( 'Gestisci le altre immagini', 'francystore-portfolio' ); ?>
							</button>
						</p>
					</div>

					<p class="fsp-box__hint">
						<?php esc_html_e( 'Gli scatti aggiuntivi, mostrati in piccolo sotto l\'immagine principale. Si aprono ingranditi al click.', 'francystore-portfolio' ); ?>
					</p>
				</div>
			</div>

			<hr class="fsp-box__sep">

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

			<p>
				<label class="fsp-label" for="fsp-field-instagram">
					<?php esc_html_e( 'Link al post Instagram', 'francystore-portfolio' ); ?>
				</label>
				<input type="url"
					class="widefat"
					id="fsp-field-instagram"
					name="<?php echo esc_attr( FSP_Meta::KEY_INSTAGRAM ); ?>"
					value="<?php echo esc_attr( FSP_Meta::get_instagram_url( $post->ID ) ); ?>"
					placeholder="https://www.instagram.com/p/XXXXXXXXXXX/">
			</p>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Il post in cui hai mostrato questo pezzo. Aprilo su Instagram, tocca i tre puntini in alto a destra e scegli "Copia link": incolla qui l\'indirizzo intero. Se lo compili, nella scheda compare il pulsante "Guardalo su Instagram". Vanno bene sia i post (/p/) sia i reel (/reel/).', 'francystore-portfolio' ); ?>
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

			<h3 class="fsp-box__title"><?php esc_html_e( 'Sfondo della scheda', 'francystore-portfolio' ); ?></h3>
			<?php self::render_media_picker( FSP_Meta::KEY_BACKGROUND, $background ); ?>
			<p class="fsp-box__hint">
				<?php esc_html_e( 'Immagine grande dietro alla scheda di questo pezzo. Se non la imposti viene usato lo sfondo della sua sezione, e in mancanza di quello lo sfondo generale del portfolio.', 'francystore-portfolio' ); ?>
			</p>

		</div>
		<?php
	}

	/**
	 * Markup di un selettore di immagine singola.
	 *
	 * @param string $field_name Nome del campo nel form.
	 * @param int    $image_id   ID dell'immagine già selezionata (0 = nessuna).
	 */
	private static function render_media_picker( $field_name, $image_id ) {
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="fsp-media" data-fsp-media>
			<div class="fsp-media__preview" data-fsp-media-preview>
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="">
				<?php endif; ?>
			</div>
			<input type="hidden"
				name="<?php echo esc_attr( $field_name ); ?>"
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
	 * Porta la descrizione del meta box nel contenuto del post.
	 *
	 * Si passa da wp_insert_post_data e non da un update dentro
	 * save_post: quello richiederebbe una seconda scrittura sul post
	 * appena salvato, con il rischio di rientrare nei propri stessi hook.
	 * Qui si interviene sui dati prima che vengano scritti, una volta
	 * sola.
	 *
	 * @param array<string,mixed> $data    Dati normalizzati in scrittura.
	 * @param array<string,mixed> $postarr Dati grezzi del post.
	 * @return array<string,mixed>
	 */
	public static function inject_description( $data, $postarr ) {
		if ( ! isset( $data['post_type'] ) || FSP_CPT::POST_TYPE !== $data['post_type'] ) {
			return $data;
		}

		// Le revisioni salvano una copia del contenuto già corretto: non
		// va reimpostato, o si sovrascriverebbe la revisione con il
		// contenuto del form corrente.
		if ( isset( $data['post_type'] ) && 'revision' === $data['post_type'] ) {
			return $data;
		}

		if ( ! isset( $_POST[ self::FIELD_DESCRIPTION ] ) ) {
			return $data;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return $data;
		}

		/*
		 * wp_kses_post e non sanitize_textarea_field: la descrizione può
		 * contenere un grassetto o un corsivo incollati da altrove, e
		 * sanitize_textarea_field li butterebbe via. wp_kses_post tiene
		 * il markup che WordPress considera sicuro nei contenuti e scarta
		 * il resto.
		 *
		 * Gli slash aggiunti da WordPress a $_POST si tolgono prima e si
		 * rimettono dopo: wp_insert_post_data riceve e restituisce dati
		 * ancora "slashati".
		 */
		$description = wp_kses_post( wp_unslash( $_POST[ self::FIELD_DESCRIPTION ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato da wp_kses_post().

		$data['post_content'] = wp_slash( $description );

		return $data;
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

		/*
		 * L'immagine principale è la featured image del post: la si
		 * imposta con le funzioni del core invece che con un meta
		 * proprio, così resta quella che WordPress usa ovunque —
		 * anteprime di condivisione comprese.
		 */
		$main_image = isset( $_POST[ self::FIELD_MAIN_IMAGE ] ) ? absint( wp_unslash( $_POST[ self::FIELD_MAIN_IMAGE ] ) ) : 0;

		if ( $main_image ) {
			set_post_thumbnail( $post_id, $main_image );
		} else {
			delete_post_thumbnail( $post_id );
		}

		$background = isset( $_POST[ FSP_Meta::KEY_BACKGROUND ] ) ? absint( wp_unslash( $_POST[ FSP_Meta::KEY_BACKGROUND ] ) ) : 0;

		if ( $background ) {
			update_post_meta( $post_id, FSP_Meta::KEY_BACKGROUND, $background );
		} else {
			delete_post_meta( $post_id, FSP_Meta::KEY_BACKGROUND );
		}

		/*
		 * Il link Instagram passa da una sanitizzazione propria, che oltre
		 * a validare l'indirizzo controlla che punti davvero a Instagram:
		 * un valore scartato torna stringa vuota e il pulsante non compare,
		 * invece di portare il visitatore su un indirizzo sbagliato.
		 */
		$instagram_raw = isset( $_POST[ FSP_Meta::KEY_INSTAGRAM ] ) ? wp_unslash( $_POST[ FSP_Meta::KEY_INSTAGRAM ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitizzato dalla riga sotto.
		$instagram     = FSP_Meta::sanitize_instagram_url( $instagram_raw );

		if ( $instagram ) {
			update_post_meta( $post_id, FSP_Meta::KEY_INSTAGRAM, $instagram );
		} else {
			delete_post_meta( $post_id, FSP_Meta::KEY_INSTAGRAM );

			// Valore scritto ma scartato: senza avviso sembrerebbe che il
			// campo non salvi, e si riproverebbe a incollare lo stesso link.
			if ( '' !== trim( (string) $instagram_raw ) ) {
				set_transient(
					'fsp_instagram_notice_' . get_current_user_id(),
					__( 'Il link Instagram non è stato salvato: deve essere un indirizzo di instagram.com, per esempio https://www.instagram.com/p/XXXXXXXXXXX/', 'francystore-portfolio' ),
					60
				);
			}
		}
	}

	/**
	 * Mostra l'avviso lasciato dal salvataggio quando un link Instagram
	 * è stato scartato.
	 *
	 * Passa da un transient perché fra il salvataggio e la schermata che
	 * lo mostra c'è un redirect: una variabile non sopravviverebbe.
	 */
	public static function print_notices() {
		$key     = 'fsp_instagram_notice_' . get_current_user_id();
		$message = get_transient( $key );

		if ( ! $message ) {
			return;
		}

		delete_transient( $key );

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
