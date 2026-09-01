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
	$fsp_gallery  = FSP_Meta::get_gallery( $fsp_id );
	$fsp_sections = get_the_terms( $fsp_id, FSP_Taxonomies::SECTION );
	$fsp_tags     = get_the_terms( $fsp_id, FSP_Taxonomies::TAG );

	$fsp_sections = is_wp_error( $fsp_sections ) || ! $fsp_sections ? array() : $fsp_sections;
	$fsp_tags     = is_wp_error( $fsp_tags ) || ! $fsp_tags ? array() : $fsp_tags;

	/*
	 * Sfondo della scheda: quello della prima sezione del pezzo, con lo
	 * sfondo generale del portfolio come riserva. Così una lampada resta
	 * sull'ambientazione delle lampade anche quando la si apre da un
	 * link diretto, senza dover impostare uno sfondo pezzo per pezzo.
	 */
	$fsp_bg_id = 0;

	foreach ( $fsp_sections as $fsp_section ) {
		$fsp_bg_id = FSP_Taxonomies::get_background_id( $fsp_section->term_id );

		if ( $fsp_bg_id ) {
			break;
		}
	}

	if ( ! $fsp_bg_id ) {
		$fsp_bg_id = FSP_Settings::get_home_background_id();
	}

	$fsp_bg_url = $fsp_bg_id ? wp_get_attachment_image_url( $fsp_bg_id, 'full' ) : '';

	$fsp_instagram_url = FSP_Settings::get_instagram_url();
	$fsp_whatsapp      = FSP_Settings::get_whatsapp_number();

	/*
	 * Riferimento che il visitatore deve citare per farsi capire: il
	 * codice pezzo se c'è, altrimenti il titolo. È il testo che il
	 * pulsante Instagram copia negli appunti.
	 */
	$fsp_reference = $fsp_codice ? $fsp_codice : get_the_title();

	$fsp_whatsapp_url = '';

	if ( $fsp_whatsapp ) {
		$fsp_whatsapp_url = 'https://wa.me/' . $fsp_whatsapp . '?text=' . rawurlencode(
			sprintf(
				/* translators: %s: codice o titolo del pezzo. */
				__( 'Ciao! Vorrei informazioni su: %s', 'francystore-portfolio' ),
				$fsp_reference
			)
		);
	}

	$fsp_prev = get_previous_post();
	$fsp_next = get_next_post();
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

<article class="fsp-single">

	<?php if ( $fsp_bg_url ) : ?>
		<div class="fsp-single__bg" aria-hidden="true">
			<img src="<?php echo esc_url( $fsp_bg_url ); ?>" alt="">
		</div>
	<?php endif; ?>

	<div class="fsp-single__overlay" aria-hidden="true"></div>
	<div class="fsp-single__vignette" aria-hidden="true"></div>
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

			<?php // Colonna sinistra: immagine principale e galleria. ?>
			<div class="fsp-single__media-col">

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="fsp-single__media">
						<button type="button"
							class="fsp-single__zoom"
							data-fsp-lightbox="<?php echo esc_url( (string) wp_get_attachment_image_url( get_post_thumbnail_id(), 'full' ) ); ?>"
							aria-label="<?php esc_attr_e( 'Ingrandisci immagine', 'francystore-portfolio' ); ?>">
							<?php the_post_thumbnail( 'large' ); ?>
						</button>
					</figure>
				<?php endif; ?>

				<?php if ( $fsp_gallery ) : ?>
					<ul class="fsp-single__gallery">
						<?php foreach ( $fsp_gallery as $fsp_image_id ) : ?>
							<?php
							$fsp_full  = wp_get_attachment_image_url( $fsp_image_id, 'full' );
							$fsp_thumb = wp_get_attachment_image( $fsp_image_id, 'medium', false, array( 'loading' => 'lazy' ) );

							// Allegato cancellato dalla Libreria Media: si salta,
							// invece di lasciare un riquadro vuoto nella galleria.
							if ( ! $fsp_full || ! $fsp_thumb ) {
								continue;
							}
							?>
							<li class="fsp-single__gallery-item">
								<button type="button"
									class="fsp-single__gallery-btn"
									data-fsp-lightbox="<?php echo esc_url( $fsp_full ); ?>"
									aria-label="<?php esc_attr_e( 'Ingrandisci immagine', 'francystore-portfolio' ); ?>">
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

					<?php if ( $fsp_sections || $fsp_tags ) : ?>
						<div class="fsp-panel__rule"></div>
						<div class="fsp-panel__terms">
							<?php
							/*
							 * I termini rimandano alla griglia con il filtro già
							 * applicato tramite querystring, non all'archivio della
							 * tassonomia. La differenza conta: sull'archivio di
							 * tassonomia il server manda in pagina soltanto i pezzi di
							 * quel termine, quindi togliendo il filtro dalla barra non
							 * ricomparirebbe nulla — le altre schede non sono proprio
							 * nel documento. Passando dalla griglia completa i filtri
							 * restano reversibili senza ricaricare.
							 */
							$fsp_archive_url = get_post_type_archive_link( FSP_CPT::POST_TYPE );
							?>
							<?php foreach ( $fsp_sections as $fsp_section ) : ?>
								<a class="fsp-term fsp-term--section" href="<?php echo esc_url( add_query_arg( 'sezione', $fsp_section->slug, $fsp_archive_url ) ); ?>">
									<?php echo esc_html( $fsp_section->name ); ?>
								</a>
							<?php endforeach; ?>
							<?php foreach ( $fsp_tags as $fsp_tag ) : ?>
								<a class="fsp-term" href="<?php echo esc_url( add_query_arg( 'tag', $fsp_tag->slug, $fsp_archive_url ) ); ?>">
									<?php echo esc_html( $fsp_tag->name ); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $fsp_instagram_url || $fsp_whatsapp_url ) : ?>
						<div class="fsp-panel__rule"></div>
						<div class="fsp-contact">
							<div class="fsp-contact__title"><?php esc_html_e( 'Ti interessa un pezzo così?', 'francystore-portfolio' ); ?></div>

							<?php if ( $fsp_instagram_url ) : ?>
								<?php
								/*
								 * Instagram non permette di precompilare il testo del
								 * messaggio: nessun link, di nessun tipo, apre la chat
								 * con una frase già scritta. Il pulsante copia allora
								 * il riferimento del pezzo negli appunti e apre il
								 * profilo, così al visitatore resta solo da incollare
								 * e a te arriva un messaggio che dice di quale oggetto
								 * si parla.
								 */
								?>
								<a class="fsp-contact__btn fsp-contact__btn--ig"
									href="<?php echo esc_url( $fsp_instagram_url ); ?>"
									target="_blank"
									rel="noopener"
									data-fsp-copy="<?php echo esc_attr( $fsp_reference ); ?>">
									<?php esc_html_e( 'Scrivimi su Instagram', 'francystore-portfolio' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $fsp_whatsapp_url ) : ?>
								<a class="fsp-contact__btn fsp-contact__btn--wa"
									href="<?php echo esc_url( $fsp_whatsapp_url ); ?>"
									target="_blank"
									rel="noopener">
									<?php esc_html_e( 'Scrivimi su WhatsApp', 'francystore-portfolio' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $fsp_instagram_url ) : ?>
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
						</div>
					<?php endif; ?>
				</section>
			</div>

		</div>

		<?php if ( get_the_content() ) : ?>
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
