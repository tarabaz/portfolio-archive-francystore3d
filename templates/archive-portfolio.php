<?php
/**
 * Template della griglia del portfolio.
 *
 * Documento HTML completo e indipendente dal tema, servito da
 * class-fsp-template-loader.php al posto dell'archive.php del tema.
 * Il tema può sovrascriverlo copiandolo in
 * wp-content/themes/<tema>/francystore-portfolio/archive-portfolio.php.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fsp_settings = FSP_Settings::get_all();
$fsp_home_bg  = FSP_Settings::get_home_background_id();

/*
 * URL diretto invece di wp_get_attachment_image(): quella funzione
 * aggiunge srcset/sizes e il browser, su finestre strette, scarica una
 * variante più piccola del file. Siccome il CSS usa la dimensione
 * naturale dell'immagine, la "naturale" diventerebbe quella della
 * variante ridotta e lo sfondo si rimpicciolirebbe invece di essere
 * tagliato ai lati.
 */
$fsp_home_bg_url = $fsp_home_bg ? wp_get_attachment_image_url( $fsp_home_bg, 'full' ) : '';

$fsp_sections = get_terms(
	array(
		'taxonomy'   => FSP_Taxonomies::SECTION,
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);

$fsp_types = get_terms(
	array(
		'taxonomy'   => FSP_Taxonomies::TAG,
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);

$fsp_sections = is_wp_error( $fsp_sections ) ? array() : $fsp_sections;
$fsp_types    = is_wp_error( $fsp_types ) ? array() : $fsp_types;

/*
 * Mappa slug sezione => URL dello sfondo, passata al JS come JSON in un
 * attributo data. Il cambio sfondo deve avvenire mentre si spuntano i
 * filtri, cioè senza ricaricare la pagina: gli URL vanno quindi già
 * tutti nel documento.
 */
$fsp_section_backgrounds = array();

foreach ( $fsp_sections as $fsp_section ) {
	$fsp_bg_id = FSP_Taxonomies::get_background_id( $fsp_section->term_id );

	if ( ! $fsp_bg_id ) {
		continue;
	}

	$fsp_bg_url = wp_get_attachment_image_url( $fsp_bg_id, 'full' );

	if ( $fsp_bg_url ) {
		$fsp_section_backgrounds[ $fsp_section->slug ] = $fsp_bg_url;
	}
}

/*
 * Su un archivio di tassonomia (ci si arriva dai link "sezione" nelle
 * schede) la query ha già filtrato i pezzi lato server. Il filtro
 * corrispondente parte quindi selezionato, così la barra rispecchia
 * quello che si sta guardando invece di dire "tutti".
 */
$fsp_preselected_section = '';
$fsp_preselected_type    = '';

if ( is_tax( FSP_Taxonomies::SECTION ) ) {
	$fsp_queried             = get_queried_object();
	$fsp_preselected_section = $fsp_queried instanceof WP_Term ? $fsp_queried->slug : '';
} elseif ( is_tax( FSP_Taxonomies::TAG ) ) {
	$fsp_queried          = get_queried_object();
	$fsp_preselected_type = $fsp_queried instanceof WP_Term ? $fsp_queried->slug : '';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="fsp-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php // Colore delle barre del browser su mobile, altrimenti campionato dallo sfondo del tema. ?>
	<meta name="theme-color" content="#08090a">
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'fsp-body fsp-body--archive' ); ?>>

<div class="fsp-archive"
	data-fsp-archive
	data-section-backgrounds="<?php echo esc_attr( wp_json_encode( $fsp_section_backgrounds ) ); ?>"
	data-home-background="<?php echo esc_url( $fsp_home_bg_url ); ?>">

	<div class="fsp-archive__bg" data-fsp-bg aria-hidden="true">
		<?php if ( $fsp_home_bg_url ) : ?>
			<img src="<?php echo esc_url( $fsp_home_bg_url ); ?>" alt="" data-fsp-bg-image>
		<?php endif; ?>
	</div>

	<div class="fsp-archive__overlay" aria-hidden="true"></div>
	<div class="fsp-scanlines" aria-hidden="true"></div>

	<div class="fsp-archive__inner">

		<header class="fsp-archive__header">
			<h1 class="fsp-archive__title fsp-display"><?php echo esc_html( $fsp_settings['header_title'] ); ?></h1>
			<?php if ( $fsp_settings['header_subtitle'] ) : ?>
				<div class="fsp-archive__subtitle"><?php echo esc_html( $fsp_settings['header_subtitle'] ); ?></div>
			<?php endif; ?>
			<?php if ( $fsp_settings['header_tag'] ) : ?>
				<div class="fsp-archive__tag"><?php echo esc_html( $fsp_settings['header_tag'] ); ?></div>
			<?php endif; ?>
			<div class="fsp-rule"></div>
			<?php require FSP_PLUGIN_DIR . 'templates/parts/topbar.php'; ?>
		</header>

		<?php if ( $fsp_sections || $fsp_types ) : ?>
			<div class="fsp-filters" data-fsp-filters>

				<?php if ( $fsp_sections ) : ?>
					<div class="fsp-filters__group" role="group" aria-label="<?php esc_attr_e( 'Filtra per sezione', 'francystore-portfolio' ); ?>">
						<div class="fsp-filters__legend"><?php esc_html_e( 'Sezioni', 'francystore-portfolio' ); ?></div>
						<div class="fsp-filters__chips">
							<?php foreach ( $fsp_sections as $fsp_section ) : ?>
								<?php $fsp_is_on = ( $fsp_section->slug === $fsp_preselected_section ); ?>
								<button type="button"
									class="fsp-chip<?php echo $fsp_is_on ? ' is-active' : ''; ?>"
									data-fsp-filter="section"
									data-value="<?php echo esc_attr( $fsp_section->slug ); ?>"
									aria-pressed="<?php echo $fsp_is_on ? 'true' : 'false'; ?>">
									<?php echo esc_html( $fsp_section->name ); ?>
									<span class="fsp-chip__count"><?php echo esc_html( (string) $fsp_section->count ); ?></span>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $fsp_types ) : ?>
					<div class="fsp-filters__group" role="group" aria-label="<?php esc_attr_e( 'Filtra per tipologia', 'francystore-portfolio' ); ?>">
						<div class="fsp-filters__legend"><?php esc_html_e( 'Tipologie', 'francystore-portfolio' ); ?></div>
						<div class="fsp-filters__chips">
							<?php foreach ( $fsp_types as $fsp_type ) : ?>
								<?php $fsp_is_on = ( $fsp_type->slug === $fsp_preselected_type ); ?>
								<button type="button"
									class="fsp-chip fsp-chip--type<?php echo $fsp_is_on ? ' is-active' : ''; ?>"
									data-fsp-filter="type"
									data-value="<?php echo esc_attr( $fsp_type->slug ); ?>"
									aria-pressed="<?php echo $fsp_is_on ? 'true' : 'false'; ?>">
									<?php echo esc_html( $fsp_type->name ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="fsp-filters__bar">
					<span class="fsp-filters__count" data-fsp-count aria-live="polite"></span>
					<button type="button" class="fsp-filters__reset" data-fsp-reset hidden>
						<?php esc_html_e( 'Azzera filtri', 'francystore-portfolio' ); ?>
					</button>
				</div>

			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

			<div class="fsp-grid" data-fsp-grid>
				<?php
				while ( have_posts() ) :
					the_post();
					require FSP_PLUGIN_DIR . 'templates/parts/card-pezzo.php';
				endwhile;
				?>
			</div>

			<p class="fsp-empty" data-fsp-empty hidden>
				<?php esc_html_e( 'Nessun pezzo corrisponde ai filtri selezionati.', 'francystore-portfolio' ); ?>
			</p>

		<?php else : ?>

			<p class="fsp-empty"><?php esc_html_e( 'Nessun pezzo ancora pubblicato.', 'francystore-portfolio' ); ?></p>

		<?php endif; ?>

	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
