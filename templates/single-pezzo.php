<?php
/**
 * Template della scheda di un singolo pezzo.
 *
 * Documento HTML completo e indipendente dal tema, servito da
 * class-fsp-template-loader.php al posto del single.php del tema. Il
 * tema può sovrascriverlo copiandolo in
 * wp-content/themes/<tema>/francystore-portfolio/single-pezzo.php.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) :
	the_post();

	$fsp_id       = get_the_ID();
	$fsp_codice   = FSP_Meta::get( $fsp_id, 'codice' );
	$fsp_rows     = FSP_Meta::get_spec_rows( $fsp_id );
	$fsp_sections = get_the_terms( $fsp_id, FSP_Taxonomies::SECTION );
	$fsp_types    = get_the_terms( $fsp_id, FSP_Taxonomies::TAG );

	$fsp_sections = is_wp_error( $fsp_sections ) || ! $fsp_sections ? array() : $fsp_sections;
	$fsp_types    = is_wp_error( $fsp_types ) || ! $fsp_types ? array() : $fsp_types;

	/*
	 * Tutte le immagini in un elenco solo, con la principale in testa. La
	 * principale compare anche fra le miniature: cliccandone una si
	 * scambia l'immagine grande, e senza la sua non si potrebbe più
	 * tornare indietro dopo il primo click.
	 */
	$fsp_images     = FSP_Meta::get_images( $fsp_id );
	$fsp_main_image = $fsp_images ? $fsp_images[0] : 0;

	// Sfondo: pezzo, poi sezione, poi generale. La scelta sta in FSP_Meta.
	$fsp_bg_id  = FSP_Meta::get_background_id( $fsp_id );
	$fsp_bg_url = $fsp_bg_id ? wp_get_attachment_image_url( $fsp_bg_id, 'full' ) : '';

	/*
	 * Riferimento che il visitatore deve citare per farsi capire: il
	 * codice pezzo se c'è, altrimenti il titolo. È il testo che il
	 * pulsante Instagram copia negli appunti.
	 */
	$fsp_reference = $fsp_codice ? $fsp_codice : get_the_title();

	// Quali pulsanti mostrare lo decide FSP_Meta, che valuta le tre fonti
	// (link del pezzo, profilo e WhatsApp dalle impostazioni) una per una.
	$fsp_contacts = FSP_Meta::get_contact_links( $fsp_id, $fsp_reference );
	$fsp_needs_copy_hint = (bool) array_filter( wp_list_pluck( $fsp_contacts, 'copy' ) );

	$fsp_prev = get_previous_post();
	$fsp_next = get_next_post();

	/*
	 * Stesso comportamento dello sfondo dell'archivio: chi arriva qui ci
	 * arriva dalla griglia, e trovare lo sfondo fermo di là e in
	 * movimento di qua sarebbe uno stacco in mezzo alla navigazione.
	 */
	$fsp_effect        = FSP_Settings::get_background_effect();
	$fsp_effect_mobile = FSP_Settings::background_effect_on_mobile();
	$fsp_single_class  = 'fsp-single fsp-single--bg-' . $fsp_effect;

	// Stesse manopole dell'archivio: senza, il fumo di sfondo della scheda
	// girerebbe con i valori di partenza invece che con quelli impostati.
	$fsp_smoke_params = wp_json_encode(
		array(
			'intensity' => FSP_Settings::get_smoke_value( 'smoke_intensity' ),
			'opacity'   => FSP_Settings::get_smoke_value( 'smoke_opacity' ),
			'speed'     => FSP_Settings::get_smoke_value( 'smoke_speed' ),
			'size'      => FSP_Settings::get_smoke_value( 'smoke_size' ),
		)
	);

	if ( ! $fsp_effect_mobile ) {
		$fsp_single_class .= ' fsp-no-effect-mobile';
	}
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="fsp-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#08090a">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'fsp-body fsp-body--single' ); ?>>

<article class="<?php echo esc_attr( $fsp_single_class ); ?>"
	data-bg-effect="<?php echo esc_attr( $fsp_effect ); ?>"
	data-bg-effect-mobile="<?php echo $fsp_effect_mobile ? '1' : '0'; ?>"
	data-smoke-color="<?php echo esc_attr( FSP_Settings::get_smoke_color() ); ?>"
	data-smoke-params="<?php echo esc_attr( $fsp_smoke_params ); ?>">

	<?php if ( $fsp_bg_url ) : ?>
		<div class="fsp-single__bg" aria-hidden="true">
			<img src="<?php echo esc_url( $fsp_bg_url ); ?>" alt="">
		</div>
	<?php endif; ?>

	<div class="fsp-smoke" data-fsp-smoke aria-hidden="true"></div>

	<div class="fsp-single__overlay" aria-hidden="true"></div>
	<div class="fsp-scanlines" aria-hidden="true"></div>

	<div class="fsp-single__inner">

		<?php require FSP_PLUGIN_DIR . 'templates/parts/topbar.php'; ?>

		<header class="fsp-single__header">
			<h1 class="fsp-single__title fsp-display"><?php the_title(); ?></h1>
			<?php if ( $fsp_codice ) : ?>
				<div class="fsp-single__code"><?php echo esc_html( $fsp_codice ); ?></div>
			<?php endif; ?>
			<div class="fsp-rule"></div>
		</header>

		<div class="fsp-single__cols">

			<?php // Colonna sinistra: immagine grande e miniature che la scambiano. ?>
			<div class="fsp-single__media-col">

				<?php if ( $fsp_main_image ) : ?>
					<?php
					$fsp_main_large = wp_get_attachment_image_url( $fsp_main_image, 'large' );
					$fsp_main_full  = wp_get_attachment_image_url( $fsp_main_image, 'full' );
					?>
					<figure class="fsp-single__media">
						<?php
						/*
						 * Due passaggi voluti: le miniature cambiano la foto qui
						 * dentro, e solo un click su questa la apre a tutto
						 * schermo. Aprendo il pieno schermo già dalla miniatura,
						 * per confrontare due scatti bisognerebbe chiudere e
						 * riaprire ogni volta.
						 */
						?>
						<button type="button"
							class="fsp-single__zoom"
							data-fsp-zoom
							data-full="<?php echo esc_url( (string) $fsp_main_full ); ?>"
							aria-label="<?php esc_attr_e( 'Apri a tutto schermo', 'francystore-portfolio' ); ?>">
							<img src="<?php echo esc_url( (string) $fsp_main_large ); ?>"
								alt="<?php echo esc_attr( the_title_attribute( array( 'echo' => false ) ) ); ?>"
								data-fsp-main-image>
						</button>
					</figure>
				<?php endif; ?>

				<?php if ( count( $fsp_images ) > 1 ) : ?>
					<ul class="fsp-single__gallery">
						<?php foreach ( $fsp_images as $fsp_index => $fsp_image_id ) : ?>
							<?php
							$fsp_large = wp_get_attachment_image_url( $fsp_image_id, 'large' );
							$fsp_full  = wp_get_attachment_image_url( $fsp_image_id, 'full' );
							$fsp_thumb = wp_get_attachment_image( $fsp_image_id, 'medium', false, array( 'loading' => 'lazy' ) );

							// Allegato cancellato dalla Libreria Media: si salta,
							// invece di lasciare un riquadro vuoto nella striscia.
							if ( ! $fsp_large || ! $fsp_thumb ) {
								continue;
							}
							?>
							<li class="fsp-single__gallery-item">
								<button type="button"
									class="fsp-single__gallery-btn<?php echo 0 === $fsp_index ? ' is-active' : ''; ?>"
									data-fsp-thumb
									data-large="<?php echo esc_url( $fsp_large ); ?>"
									data-full="<?php echo esc_url( (string) $fsp_full ); ?>"
									aria-label="<?php esc_attr_e( 'Mostra questa immagine', 'francystore-portfolio' ); ?>">
									<?php echo $fsp_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup generato dal core. ?>
								</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

			</div>

			<?php // Colonna destra: pannello con i dati tecnici. ?>
			<div class="fsp-single__data-col">
				<section class="fsp-panel">
					<span class="fsp-panel__screw fsp-panel__screw--tl" aria-hidden="true"></span>
					<span class="fsp-panel__screw fsp-panel__screw--tr" aria-hidden="true"></span>
					<span class="fsp-panel__screw fsp-panel__screw--bl" aria-hidden="true"></span>
					<span class="fsp-panel__screw fsp-panel__screw--br" aria-hidden="true"></span>

					<h2 class="fsp-panel__heading"><?php esc_html_e( 'Scheda tecnica', 'francystore-portfolio' ); ?></h2>
					<div class="fsp-panel__rule"></div>

					<?php if ( $fsp_rows ) : ?>
						<dl class="fsp-panel__specs">
							<?php foreach ( $fsp_rows as $fsp_row ) : ?>
								<div class="fsp-panel__spec">
									<dt><?php echo esc_html( $fsp_row['label'] ); ?></dt>
									<dd><?php echo esc_html( $fsp_row['value'] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php else : ?>
						<p class="fsp-panel__empty"><?php esc_html_e( 'Scheda tecnica non ancora compilata.', 'francystore-portfolio' ); ?></p>
					<?php endif; ?>

					<?php if ( $fsp_sections || $fsp_types ) : ?>
						<div class="fsp-panel__rule"></div>
						<?php
						/*
						 * Sezioni e tipologie sono etichette, non link: da qui
						 * si è arrivati per guardare il pezzo, e un rimando
						 * alla griglia filtrata porterebbe via dalla scheda
						 * proprio dove invece si vuole leggere e poi scrivere.
						 */
						?>
						<div class="fsp-panel__terms">
							<?php foreach ( $fsp_sections as $fsp_section ) : ?>
								<span class="fsp-term fsp-term--section"><?php echo esc_html( $fsp_section->name ); ?></span>
							<?php endforeach; ?>
							<?php foreach ( $fsp_types as $fsp_type ) : ?>
								<span class="fsp-term"><?php echo esc_html( $fsp_type->name ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

				</section>

				<?php
				/*
				 * I contatti stanno in un riquadro proprio, sotto la scheda:
				 * dentro al pannello, in coda alle specifiche, il pulsante si
				 * confondeva con i dati tecnici invece di leggersi come
				 * l'invito ad agire che è.
				 */
				?>
				<?php if ( $fsp_contacts ) : ?>
					<section class="fsp-contact">
						<div class="fsp-contact__title"><?php esc_html_e( 'Ti interessa un pezzo così?', 'francystore-portfolio' ); ?></div>

						<?php foreach ( $fsp_contacts as $fsp_contact ) : ?>
							<a class="fsp-contact__btn fsp-contact__btn--<?php echo esc_attr( $fsp_contact['variant'] ); ?>"
								href="<?php echo esc_url( $fsp_contact['url'] ); ?>"
								target="_blank"
								rel="noopener"
								<?php if ( $fsp_contact['copy'] ) : ?>
									data-fsp-copy="<?php echo esc_attr( $fsp_contact['copy'] ); ?>"
								<?php endif; ?>>
								<?php echo esc_html( $fsp_contact['label'] ); ?>
							</a>
						<?php endforeach; ?>

						<?php if ( $fsp_needs_copy_hint ) : ?>
							<p class="fsp-contact__hint">
								<?php
								printf(
									/* translators: %s: codice o titolo del pezzo. */
									esc_html__( 'Cita il riferimento %s nel messaggio: il pulsante te lo copia già negli appunti.', 'francystore-portfolio' ),
									'<strong>' . esc_html( $fsp_reference ) . '</strong>'
								);
								?>
							</p>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<?php // La descrizione estesa segue la scheda tecnica, nella stessa colonna. ?>
				<?php if ( trim( (string) get_the_content() ) ) : ?>
					<section class="fsp-note">
						<div class="fsp-note__head">
							<?php esc_html_e( 'Note di lavorazione', 'francystore-portfolio' ); ?>
							<span class="fsp-note__rule"></span>
							<?php if ( $fsp_codice ) : ?>
								<span><?php echo esc_html( $fsp_codice ); ?></span>
							<?php endif; ?>
						</div>
						<div class="fsp-note__text">
							<?php the_content(); ?>
						</div>
					</section>
				<?php endif; ?>
			</div>

		</div>

		<?php if ( $fsp_prev || $fsp_next ) : ?>
			<nav class="fsp-pager" aria-label="<?php esc_attr_e( 'Altri pezzi', 'francystore-portfolio' ); ?>">
				<?php if ( $fsp_next ) : ?>
					<a class="fsp-pager__link" href="<?php echo esc_url( (string) get_permalink( $fsp_next ) ); ?>">
						<span class="fsp-pager__dir"><?php esc_html_e( '← Più recente', 'francystore-portfolio' ); ?></span>
						<span class="fsp-pager__title"><?php echo esc_html( get_the_title( $fsp_next ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $fsp_prev ) : ?>
					<a class="fsp-pager__link fsp-pager__link--next" href="<?php echo esc_url( (string) get_permalink( $fsp_prev ) ); ?>">
						<span class="fsp-pager__dir"><?php esc_html_e( 'Più vecchio →', 'francystore-portfolio' ); ?></span>
						<span class="fsp-pager__title"><?php echo esc_html( get_the_title( $fsp_prev ) ); ?></span>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

	</div>
</article>

<?php // Sovrapposizione per l'ingrandimento delle foto, riempita dal JS al click. ?>
<div class="fsp-lightbox" data-fsp-lightbox-overlay hidden>
	<button type="button" class="fsp-lightbox__close" data-fsp-lightbox-close aria-label="<?php esc_attr_e( 'Chiudi', 'francystore-portfolio' ); ?>">&times;</button>
	<img src="" alt="" data-fsp-lightbox-image>
</div>

<?php wp_footer(); ?>
</body>
</html>
	<?php
	break; // La scheda mostra un solo pezzo per richiesta.
endwhile;
