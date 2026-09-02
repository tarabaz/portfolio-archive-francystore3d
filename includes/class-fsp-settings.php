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
			'header_logo'            => 0,
			'header_logo_height'     => 90,
			'home_background_image'  => 0,
			'background_effect'      => 'scroll',
			'background_effect_mobile' => '',
			'logo_smoke'             => '',
			'smoke_color'            => '#8fb6c8',
			'smoke_intensity'        => 55,
			'smoke_opacity'          => 55,
			'smoke_speed'            => 40,
			'smoke_size'             => 55,
			'logo_opacity'           => 100,
			'logo_glow'              => 45,
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
	 * Modi possibili per lo sfondo del portfolio.
	 *
	 * @return array<string,string> valore salvato => etichetta.
	 */
	public static function get_background_effects() {
		return array(
			'scroll' => __( 'Scorre con la pagina', 'francystore-portfolio' ),
			'fixed'  => __( 'Resta fermo mentre scorri', 'francystore-portfolio' ),
			'smoke'  => __( 'Fermo, con fumo animato sopra', 'francystore-portfolio' ),
		);
	}

	/**
	 * Effetto scelto per lo sfondo.
	 *
	 * @return string
	 */
	public static function get_background_effect() {
		$value = (string) self::get( 'background_effect' );

		return array_key_exists( $value, self::get_background_effects() ) ? $value : 'scroll';
	}

	/**
	 * True se l'effetto va mostrato anche su telefono.
	 *
	 * Di default è spento: sfondo fermo e fumo animato costano entrambi
	 * a un telefono — il primo in ridisegni durante lo scorrimento, il
	 * secondo in batteria — e chi guarda il portfolio dal telefono ci
	 * arriva quasi sempre da Instagram, cioè da una connessione dati e
	 * con l'app aperta di fianco.
	 *
	 * @return bool
	 */
	public static function background_effect_on_mobile() {
		return '1' === (string) self::get( 'background_effect_mobile' );
	}

	/**
	 * True se l'intestazione deve immergere il logo nel fumo.
	 *
	 * @return bool
	 */
	public static function logo_in_smoke() {
		return '1' === (string) self::get( 'logo_smoke' );
	}

	/**
	 * Colore del fumo, in esadecimale.
	 *
	 * @return string
	 */
	public static function get_smoke_color() {
		$color = (string) self::get( 'smoke_color' );

		return preg_match( '/^#[0-9a-f]{6}$/i', $color ) ? $color : '#8fb6c8';
	}

	/**
	 * Manopole dell'effetto fumo: chiave => etichetta e spiegazione.
	 *
	 * Sono tutte in centesimi invece che nelle unità che servono davvero
	 * (numero di volute, secondi, pixel): così si ragiona in "quanto" e
	 * non in "quanti", e i valori restano confrontabili fra loro. La
	 * conversione nelle unità vere la fa il JavaScript.
	 *
	 * @return array<string,array{label:string,help:string}>
	 */
	public static function get_smoke_controls() {
		return array(
			'smoke_intensity' => array(
				'label' => __( 'Intensità', 'francystore-portfolio' ),
				'help'  => __( 'Quante volute ci sono. Alzando si infittisce, abbassando resta più rarefatto. È il valore che pesa di più sul lavoro del browser: oltre 80 su uno schermo grande si inizia a sentire.', 'francystore-portfolio' ),
			),
			'smoke_opacity'   => array(
				'label' => __( 'Opacità', 'francystore-portfolio' ),
				'help'  => __( 'Quanto è marcata ogni voluta. Le volute si sommano fra loro, quindi valori alti tendono a bruciare in bianco dove si sovrappongono.', 'francystore-portfolio' ),
			),
			'smoke_speed'     => array(
				'label' => __( 'Velocità', 'francystore-portfolio' ),
				'help'  => __( 'Quanto si muove il fumo. Sotto il 30 sembra quasi fermo, sopra il 70 diventa vento più che fumo.', 'francystore-portfolio' ),
			),
			'smoke_size'      => array(
				'label' => __( 'Dimensione delle volute', 'francystore-portfolio' ),
				'help'  => __( 'Volute grandi danno una nebbia morbida e uniforme, volute piccole uno sbuffo più definito e riconoscibile.', 'francystore-portfolio' ),
			),
			'logo_opacity'    => array(
				'label' => __( 'Opacità del logo', 'francystore-portfolio' ),
				'help'  => __( 'Abbassandola il logo si fonde di più con il fumo, come se fosse dentro la nebbia invece che davanti.', 'francystore-portfolio' ),
			),
			'logo_glow'       => array(
				'label' => __( 'Bagliore del logo', 'francystore-portfolio' ),
				'help'  => __( 'Alone luminoso attorno alle forme del logo, del colore del fumo. Rende al meglio con un logo di un colore solo e chiaro su sfondo trasparente: su un logo con parti scure l\'alone si vede poco, perché segue i contorni di ciò che è già illuminato. A 0 è spento.', 'francystore-portfolio' ),
			),
		);
	}

	/**
	 * Valore di una manopola del fumo, da 0 a 100.
	 *
	 * @param string $key Chiave dell'impostazione.
	 * @return int
	 */
	public static function get_smoke_value( $key ) {
		$value = self::get( $key );

		return null === $value ? 50 : max( 0, min( 100, (int) $value ) );
	}

	/**
	 * ID del logo mostrato in cima al portfolio al posto del titolo.
	 *
	 * @return int 0 se non impostato: in quel caso si stampa il titolo.
	 */
	public static function get_header_logo_id() {
		return (int) self::get( 'header_logo' );
	}

	/**
	 * Altezza del logo in pixel.
	 *
	 * Si imposta l'altezza e non la larghezza perché è l'altezza a
	 * decidere quanto spazio verticale il logo si prende prima della
	 * griglia; la larghezza segue da sé, quali che siano le proporzioni
	 * del file caricato.
	 *
	 * @return int
	 */
	public static function get_header_logo_height() {
		$height = (int) self::get( 'header_logo_height' );

		return $height > 0 ? $height : 90;
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
		$output['header_logo']           = isset( $input['header_logo'] ) ? absint( $input['header_logo'] ) : 0;

		$effect                     = isset( $input['background_effect'] ) ? sanitize_key( $input['background_effect'] ) : 'scroll';
		$output['background_effect'] = array_key_exists( $effect, self::get_background_effects() ) ? $effect : 'scroll';

		// Una casella non spuntata non viene inviata dal browser: l'assenza
		// vale come "no", non come "lascia com'era".
		$output['background_effect_mobile'] = ! empty( $input['background_effect_mobile'] ) ? '1' : '';
		$output['logo_smoke']               = ! empty( $input['logo_smoke'] ) ? '1' : '';

		$color                  = isset( $input['smoke_color'] ) ? sanitize_hex_color( $input['smoke_color'] ) : '';
		$output['smoke_color'] = $color ? $color : '#8fb6c8';

		// Le manopole restano dentro 0-100: un valore fuori scala viene
		// riportato al limite invece di far fallire tutto il salvataggio.
		foreach ( array_keys( self::get_smoke_controls() ) as $control ) {
			if ( ! isset( $input[ $control ] ) ) {
				continue;
			}

			$output[ $control ] = max( 0, min( 100, absint( $input[ $control ] ) ) );
		}

		/*
		 * Altezza del logo entro limiti ragionevoli: sotto i 20px sarebbe
		 * illeggibile e sopra i 400 spingerebbe la griglia fuori dalla
		 * prima schermata. Un valore fuori scala viene riportato dentro
		 * invece di essere rifiutato, così un refuso non blocca il
		 * salvataggio di tutto il resto.
		 */
		$logo_height               = isset( $input['header_logo_height'] ) ? absint( $input['header_logo_height'] ) : 0;
		$output['header_logo_height'] = $logo_height ? min( 400, max( 20, $logo_height ) ) : 90;
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
		$logo_id       = (int) $settings['header_logo'];
		$logo          = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
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
							<label><?php esc_html_e( 'Logo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<div class="fsp-media" data-fsp-media>
								<div class="fsp-media__preview" data-fsp-media-preview>
									<?php if ( $logo ) : ?>
										<img src="<?php echo esc_url( $logo ); ?>" alt="">
									<?php endif; ?>
								</div>
								<input type="hidden"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[header_logo]"
									value="<?php echo esc_attr( (string) $logo_id ); ?>"
									data-fsp-media-input>
								<button type="button" class="button" data-fsp-media-select>
									<?php esc_html_e( 'Scegli immagine', 'francystore-portfolio' ); ?>
								</button>
								<button type="button" class="button-link fsp-media__remove" data-fsp-media-remove<?php echo $logo_id ? '' : ' hidden'; ?>>
									<?php esc_html_e( 'Rimuovi', 'francystore-portfolio' ); ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Compare in cima al portfolio al posto del titolo scritto. Se non lo carichi viene mostrato il titolo qui sotto. Meglio un PNG con lo sfondo trasparente: la pagina è scura.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fsp-logo-height"><?php esc_html_e( 'Altezza del logo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="fsp-logo-height"
								class="small-text"
								min="20"
								max="400"
								step="1"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[header_logo_height]"
								value="<?php echo esc_attr( (string) $settings['header_logo_height'] ); ?>"> px
							<p class="description">
								<?php esc_html_e( 'Si imposta l\'altezza e non la larghezza: è l\'altezza a decidere quanto spazio il logo si prende prima della griglia, e la larghezza segue da sé qualunque siano le proporzioni del file. Fra 20 e 400 px.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logo immerso nel fumo', 'francystore-portfolio' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[logo_smoke]"
									value="1"
									<?php checked( self::logo_in_smoke() ); ?>>
								<?php esc_html_e( 'Fai passare volute di fumo dietro e davanti al logo', 'francystore-portfolio' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Il logo viene disegnato in mezzo al fumo: una parte delle volute gli passa dietro e una davanti, sommandosi alla luce come in una foto in controluce. Funziona anche senza logo, sul titolo scritto. Segue la stessa scelta fatta sopra per i telefoni.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="fsp-smoke-color"><?php esc_html_e( 'Colore del fumo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<input type="color"
								id="fsp-smoke-color"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>[smoke_color]"
								value="<?php echo esc_attr( self::get_smoke_color() ); ?>">
							<p class="description">
								<?php esc_html_e( 'Vale sia per il fumo dell\'intestazione sia per quello di sfondo. Le volute si sommano fra loro: un colore già chiaro tende a bruciare in bianco dove si sovrappongono, meglio partire da una tinta media.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
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
							<p class="description">
								<?php esc_html_e( 'Mostrato solo se non hai caricato un logo.', 'francystore-portfolio' ); ?>
							</p>
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
					<tr>
						<th scope="row">
							<label for="fsp-bg-effect"><?php esc_html_e( 'Comportamento dello sfondo', 'francystore-portfolio' ); ?></label>
						</th>
						<td>
							<select id="fsp-bg-effect" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[background_effect]">
								<?php foreach ( self::get_background_effects() as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['background_effect'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Con "resta fermo" il portfolio scorre sopra allo sfondo, che non si muove. Il fumo animato aggiunge sopra allo sfondo delle volute lente disegnate dal browser: fa scena, ma è JavaScript che gira di continuo.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Effetto su telefono', 'francystore-portfolio' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[background_effect_mobile]"
									value="1"
									<?php checked( self::background_effect_on_mobile() ); ?>>
								<?php esc_html_e( 'Usa l\'effetto anche su telefono', 'francystore-portfolio' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Meglio lasciarlo spento. Sfondo fermo e fumo animato costano entrambi a un telefono — il primo in ridisegni durante lo scorrimento, il secondo in batteria — e chi guarda il portfolio dal cellulare ci arriva quasi sempre da Instagram, con l\'app aperta di fianco. Spento, su telefono torna lo sfondo normale che scorre con la pagina.', 'francystore-portfolio' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Regolazione del fumo', 'francystore-portfolio' ); ?></h2>
				<p class="description fsp-settings__intro">
					<?php esc_html_e( 'Tutti da 0 a 100. Valgono sia per il fumo dell\'intestazione sia per quello di sfondo. Non c\'è una combinazione giusta: dipende dal logo e dalla foto che ci metti dietro, quindi cambia un valore per volta e ricarica il portfolio per vedere l\'effetto.', 'francystore-portfolio' ); ?>
				</p>

				<table class="form-table fsp-smoke-table" role="presentation">
					<?php foreach ( self::get_smoke_controls() as $key => $control ) : ?>
						<tr>
							<th scope="row">
								<label for="fsp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $control['label'] ); ?></label>
							</th>
							<td>
								<?php
								/*
								 * Cursore e casella numerica insieme, legati fra loro
								 * dal JavaScript: il cursore serve a cercare il valore
								 * a occhio, la casella a rimetterci esattamente quello
								 * che si era trovato buono la volta prima.
								 */
								?>
								<input type="range"
									class="fsp-range"
									id="fsp-<?php echo esc_attr( $key ); ?>"
									min="0"
									max="100"
									step="1"
									value="<?php echo esc_attr( (string) self::get_smoke_value( $key ) ); ?>"
									data-fsp-range="<?php echo esc_attr( $key ); ?>">
								<input type="number"
									class="small-text fsp-range__value"
									min="0"
									max="100"
									step="1"
									name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( (string) self::get_smoke_value( $key ) ); ?>"
									data-fsp-range-value="<?php echo esc_attr( $key ); ?>">
								<p class="description"><?php echo esc_html( $control['help'] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<p class="description fsp-settings__intro">
					<?php esc_html_e( 'Un punto di partenza che funziona quasi sempre: intensità 55, opacità 55, velocità 40, dimensione 55, logo 100. Da lì alza l\'intensità se il fumo si perde sullo sfondo, e abbassa l\'opacità del logo se lo vuoi più immerso.', 'francystore-portfolio' ); ?>
				</p>

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
