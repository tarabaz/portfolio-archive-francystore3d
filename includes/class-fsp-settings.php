<?php
/**
 * Pagina impostazioni del plugin.
 *
 * Raccoglie quello che va cambiato senza toccare il codice: indirizzo
 * pubblico dell'archivio, testi e sfondo dell'intestazione, contatti e
 * l'elenco di etichette suggerite per gli attributi liberi.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Settings {

	/** Opzione che contiene tutte le impostazioni del plugin. */
	const OPTION_NAME = 'fsp_settings';

	/** Gruppo della Settings API. */
	const OPTION_GROUP = 'fsp_settings_group';

	/** Slug della pagina impostazioni in wp-admin. */
	const PAGE_SLUG = 'fsp-settings';

	/** Hook suffix della pagina, valorizzato dopo la registrazione. */
	private static $page_hook = '';

	/**
	 * Valori di partenza, usati anche come fallback quando una singola
	 * chiave manca (succede alle installazioni aggiornate da una
	 * versione che non aveva ancora quel campo).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'archive_slug'           => FSP_CPT::DEFAULT_SLUG,
			'header_title'           => 'FRANCYSTORE3D',
			'header_subtitle'        => '— PORTFOLIO —',
			'header_tag'             => 'PEZZI REALIZZATI A MANO',
			'home_background_image'  => 0,
			'instagram_handle'       => '',
			'whatsapp_number'        => '',
			'attribute_suggestions'  => "Alimentazione\nTipo illuminazione\nScala\nBase inclusa\nVerniciatura\nPeso\nPersonalizzabile",
		);
	}

	/**
	 * Aggancia pagina, opzioni e assets.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( __CLASS__, 'after_save' ), 10, 2 );
	}

	/**
	 * Tutte le impostazioni, con i default applicati alle chiavi mancanti.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		$saved = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
	}

	/**
	 * Legge una singola impostazione.
	 *
	 * @param string $key Chiave dell'impostazione.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::get_all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Slug pubblico dell'archivio.
	 *
	 * @return string
	 */
	public static function get_archive_slug() {
		$slug = sanitize_title( (string) self::get( 'archive_slug' ) );

		// Uno slug vuoto registrerebbe il CPT sulla radice del sito,
		// prendendosi le richieste di tutte le altre pagine.
		return $slug ? $slug : FSP_CPT::DEFAULT_SLUG;
	}

	/**
	 * ID dell'immagine di sfondo generale del portfolio.
	 *
	 * @return int
	 */
	public static function get_home_background_id() {
		return (int) self::get( 'home_background_image' );
	}

	/**
	 * Handle Instagram senza la chiocciola.
	 *
	 * @return string
	 */
	public static function get_instagram_handle() {
		return ltrim( (string) self::get( 'instagram_handle' ), '@' );
	}

	/**
	 * URL del profilo Instagram, vuoto se l'handle non è impostato.
	 *
	 * @return string
	 */
	public static function get_instagram_url() {
		$handle = self::get_instagram_handle();

		return $handle ? 'https://instagram.com/' . rawurlencode( $handle ) : '';
	}

	/**
	 * Numero WhatsApp in formato internazionale, sole cifre.
	 *
	 * @return string
	 */
	public static function get_whatsapp_number() {
		return preg_replace( '/\D+/', '', (string) self::get( 'whatsapp_number' ) );
	}

	/**
	 * Etichette suggerite per gli attributi liberi.
	 *
	 * @return string[]
	 */
	public static function get_attribute_suggestions() {
		$raw   = (string) self::get( 'attribute_suggestions' );
		$lines = preg_split( '/\R/', $raw );

		if ( ! is_array( $lines ) ) {
			return array();
		}

		$lines = array_map( 'trim', $lines );
		$lines = array_filter( $lines );

		return array_values( array_unique( $lines ) );
	}

	/**
	 * Dopo il salvataggio: rigenera le rewrite rules se è cambiato lo
	 * slug e invita i plugin di cache a rigenerare le pagine.
	 *
	 * Il flush va fatto solo quando serve davvero: è un'operazione
	 * costosa e chiamarla ad ogni salvataggio rallenterebbe l'admin
	 * senza motivo.
	 *
	 * @param mixed $old_value Valore precedente dell'opzione.
	 * @param mixed $value     Nuovo valore.
	 */
	public static function after_save( $old_value, $value ) {
		$old_slug = is_array( $old_value ) && isset( $old_value['archive_slug'] ) ? $old_value['archive_slug'] : '';
		$new_slug = is_array( $value ) && isset( $value['archive_slug'] ) ? $value['archive_slug'] : '';

		if ( $old_slug !== $new_slug ) {
			// Il CPT è già registrato con lo slug vecchio in questa
			// richiesta: lo si rilancia con quello nuovo prima del flush,
			// altrimenti le regole rigenerate sarebbero ancora le vecchie.
			FSP_CPT::register_post_type();
			flush_rewrite_rules();
		}

		self::flush_page_caches();
	}

	/**
	 * Invita i principali plugin di cache a rigenerare le pagine.
	 *
	 * Sfondi e testi dell'intestazione finiscono nell'HTML, non nel
	 * file .css: con una cache attiva la pagina già salvata
	 * continuerebbe a essere servita con i valori vecchi, facendo
	 * sembrare che l'impostazione non funzioni. Ogni chiamata è
	 * protetta da function_exists/class_exists, quindi su un sito senza
	 * cache non succede nulla.
	 */
	public static function flush_page_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {          // WP Rocket
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {               // W3 Total Cache
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {         // WP Super Cache
			wp_cache_clear_cache();
		}
		if ( class_exists( 'LiteSpeed\Purge' ) ) {                 // LiteSpeed Cache
			do_action( 'litespeed_purge_all' );
		}
		if ( class_exists( 'autoptimizeCache' ) ) {                // Autoptimize
			autoptimizeCache::clearall();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {    // SiteGround Optimizer
			sg_cachepress_purge_cache();
		}

		wp_cache_flush();
	}

	/**
	 * Carica gli assets admin nella sola pagina impostazioni.
	 *
	 * @param string $hook Hook della pagina admin corrente.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( $hook !== self::$page_hook ) {
			return;
		}

		FSP_Metabox::enqueue_media_picker();
	}

	/**
	 * Aggiunge "Impostazioni" come sottomenu del CPT portfolio.
	 */
	public static function register_settings_page() {
		self::$page_hook = add_submenu_page(
			'edit.php?post_type=' . FSP_CPT::POST_TYPE,
			__( 'Impostazioni Portfolio', 'francystore-portfolio' ),
			__( 'Impostazioni', 'francystore-portfolio' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Registra l'opzione con la Settings API.
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Normalizza le impostazioni prima del salvataggio.
	 *
	 * @param mixed $input Valori grezzi dal form.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = self::get_defaults();

		$output['archive_slug'] = isset( $input['archive_slug'] ) ? sanitize_title( $input['archive_slug'] ) : '';

		if ( '' === $output['archive_slug'] ) {
			$output['archive_slug'] = FSP_CPT::DEFAULT_SLUG;

			add_settings_error(
				self::OPTION_NAME,
				'fsp_empty_slug',
				__( 'L\'indirizzo dell\'archivio non può essere vuoto: è stato reimpostato su "portfolio".', 'francystore-portfolio' ),
				'error'
			);
		}

		foreach ( array( 'header_title', 'header_subtitle', 'header_tag', 'instagram_handle' ) as $key ) {
			$output[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		$output['home_background_image'] = isset( $input['home_background_image'] ) ? absint( $input['home_background_image'] ) : 0;
		$output['whatsapp_number']       = isset( $input['whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) $input['whatsapp_number'] ) : '';
		$output['attribute_suggestions']  = isset( $input['attribute_suggestions'] ) ? sanitize_textarea_field( $input['attribute_suggestions'] ) : '';

		return $output;
	}

	/**
	 * Stampa la pagina impostazioni.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = self::get_all();
		$archive_link  = get_post_type_archive_link( FSP_CPT::POST_TYPE );
		$background_id = (int) $settings['home_background_image'];
		$background    = $background_id ? wp_get_attachment_image_url( $background_id, 'medium' ) : '';
		?>
		<div class="wrap fsp-settings">
			<h1><?php esc_html_e( 'Impostazioni Portfolio', 'francystore-portfolio' ); ?></h1>

			<?php if ( $archive_link ) : ?>
				<p class="fsp-settings__link">
					<?php esc_html_e( 'Il portfolio è online a questo indirizzo:', 'francystore-portfolio' ); ?>
					<a href="<?php echo esc_url( $archive_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $archive_link ); ?></a>
				</p>
			<?php endif; ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Indirizzo', 'francystore-portfolio' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fsp-archive-slug"><?php esc_html_e( 'Indirizzo dell\'archivio', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input type="text"
								id="fsp-archive-slug"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[archive_slug]"
								value="<?php echo esc_attr( $settings['archive_slug'] ); ?>">
							<p class="description">
								<?php esc_html_e( 'Solo lettere minuscole e trattini. Non usare un indirizzo già occupato da un\'altra pagina o da un altro plugin: le due si ruberebbero le visite a vicenda.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Intestazione del portfolio', 'francystore-portfolio' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fsp-header-title"><?php esc_html_e( 'Titolo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fsp-header-title"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[header_title]"
								value="<?php echo esc_attr( $settings['header_title'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fsp-header-subtitle"><?php esc_html_e( 'Sottotitolo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fsp-header-subtitle"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[header_subtitle]"
								value="<?php echo esc_attr( $settings['header_subtitle'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fsp-header-tag"><?php esc_html_e( 'Riga sotto il titolo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fsp-header-tag"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[header_tag]"
								value="<?php echo esc_attr( $settings['header_tag'] ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Immagine di sfondo del portfolio', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<div class="fsp-media" data-fsp-media>
								<div class="fsp-media__preview" data-fsp-media-preview>
									<?php if ( $background ) : ?>
										<img src="<?php echo esc_url( $background ); ?>" alt="">
									<?php endif; ?>
								</div>
								<input type="hidden"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[home_background_image]"
									value="<?php echo esc_attr( (string) $background_id ); ?>"
									data-fsp-media-input>
								<button type="button" class="button" data-fsp-media-select>
									<?php esc_html_e( 'Scegli immagine', 'francystore-portfolio' ); ?>
								</button>
								<button type="button" class="button-link fsp-media__remove" data-fsp-media-remove<?php echo $background_id ? '' : ' hidden'; ?>>
									<?php esc_html_e( 'Rimuovi', 'francystore-portfolio' ); ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Sfondo di partenza della griglia. Quando filtri su una sola sezione viene sostituito dallo sfondo di quella sezione, se ne ha uno.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Contatti', 'francystore-portfolio' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fsp-instagram"><?php esc_html_e( 'Profilo Instagram', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fsp-instagram"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[instagram_handle]"
								value="<?php echo esc_attr( $settings['instagram_handle'] ); ?>"
								placeholder="francystore3d">
							<p class="description">
								<?php esc_html_e( 'Senza la chiocciola. Instagram non permette di precompilare il messaggio: il pulsante della scheda copia negli appunti il codice del pezzo, così chi ti scrive ha già pronto il riferimento da incollare.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fsp-whatsapp"><?php esc_html_e( 'Numero WhatsApp (facoltativo)', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fsp-whatsapp"
								class="regular-text"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[whatsapp_number]"
								value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>"
								placeholder="39XXXXXXXXXX">
							<p class="description">
								<?php esc_html_e( 'In formato internazionale, senza + e senza spazi. Se lo compili, accanto al pulsante Instagram compare anche quello WhatsApp — che a differenza di Instagram apre la chat con il messaggio già scritto, quindi converte meglio. Lascia vuoto per non mostrarlo.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Compilazione', 'francystore-portfolio' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="fsp-suggestions"><?php esc_html_e( 'Etichette suggerite', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<textarea id="fsp-suggestions"
								class="large-text code"
								rows="8"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[attribute_suggestions]"><?php echo esc_textarea( $settings['attribute_suggestions'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Una per riga. Compaiono come suggerimento mentre compili la tabella "Altri dati" di un pezzo, così le stesse etichette restano scritte allo stesso modo su tutto l\'archivio. Non limitano nulla: puoi sempre scrivere un\'etichetta che non è in elenco.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p class="fsp-settings__version">
				<?php
				printf(
					/* translators: %s: numero di versione del plugin. */
					esc_html__( 'FrancyStore Portfolio versione %s', 'francystore-portfolio' ),
					esc_html( FSP_VERSION )
				);
				?>
			</p>
		</div>
		<?php
	}
}
