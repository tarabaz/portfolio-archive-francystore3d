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

$fsp_settings    = FSP_Settings::get_all();
$fsp_home_bg     = FSP_Settings::get_home_background_id();
$fsp_logo_id     = FSP_Settings::get_header_logo_id();
$fsp_logo_height = FSP_Settings::get_header_logo_height();
$fsp_logo_url    = $fsp_logo_id ? wp_get_attachment_image_url( $fsp_logo_id, 'full' ) : '';

/*
 * Comportamento dello sfondo. La classe la mette il PHP e non il JS
 * perché lo sfondo fermo è solo CSS: passando dal JavaScript, chi ha lo
 * script bloccato vedrebbe lo sfondo scorrere e basta. Il fumo invece è
 * per forza JavaScript, e il suo canvas viene aggiunto dallo script solo
 * dove serve davvero.
 */
$fsp_effect        = FSP_Settings::get_background_effect();
$fsp_effect_mobile = FSP_Settings::background_effect_on_mobile();
$fsp_archive_class = 'fsp-archive fsp-archive--bg-' . $fsp_effect;

if ( ! $fsp_effect_mobile ) {
	$fsp_archive_class .= ' fsp-no-effect-mobile';
}

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

<div class="<?php echo esc_attr( $fsp_archive_class ); ?>"
	data-fsp-archive
	data-bg-effect="<?php echo esc_attr( $fsp_effect ); ?>"
	data-bg-effect-mobile="<?php echo $fsp_effect_mobile ? '1' : '0'; ?>"
	data-section-backgrounds="<?php echo esc_attr( wp_json_encode( $fsp_section_backgrounds ) ); ?>"
	data-home-background="<?php echo esc_url( $fsp_home_bg_url ); ?>">

	<div class="fsp-archive__bg" data-fsp-bg aria-hidden="true">
		<?php if ( $fsp_home_bg_url ) : ?>
			<img src="<?php echo esc_url( $fsp_home_bg_url ); ?>" alt="" data-fsp-bg-image>
		<?php endif; ?>
	</div>

	<?php // Il canvas del fumo lo crea il JavaScript qui dentro, se l'effetto è attivo. ?>
	<div class="fsp-smoke" data-fsp-smoke aria-hidden="true"></div>

	<div class="fsp-archive__overlay" aria-hidden="true"></div>
	<div class="fsp-scanlines" aria-hidden="true"></div>

	<div class="fsp-archive__inner">

		<header class="fsp-archive__header">
			<?php
			/*
			 * Il logo sostituisce il titolo, non lo affianca: due
			 * intestazioni una sopra l'altra si farebbero concorrenza. Se
			 * il logo non c'è resta il titolo scritto, così l'intestazione
			 * non è mai vuota.
			 *
			 * L'altezza passa da una variabile CSS inline invece che da un
			 * height diretto: il CSS può così usarla anche per le
			 * proporzioni su schermo stretto, dove il logo va rimpicciolito.
			 */
			?>
			<?php if ( $fsp_logo_url ) : ?>
				<h1 class="fsp-archive__logo" style="--fsp-logo-height: <?php echo esc_attr( (string) $fsp_logo_height ); ?>px">
					<img src="<?php echo esc_url( $fsp_logo_url ); ?>"
						alt="<?php echo esc_attr( $fsp_settings['header_title'] ? $fsp_settings['header_title'] : get_bloginfo( 'name' ) ); ?>">
				</h1>
			<?php else : ?>
				<h1 class="fsp-archive__title fsp-display"><?php echo esc_html( $fsp_settings['header_title'] ); ?></h1>
			<?php endif; ?>
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

				<?php
				/*
				 * I filtri partono chiusi: a pagina aperta il portfolio deve
				 * mostrare i pezzi, non un pannello di comandi. Il pulsante
				 * porta il numero di filtri attivi, così a pannello chiuso si
				 * capisce comunque che la griglia è filtrata.
				 *
				 * L'attributo hidden è messo dal PHP e tolto dal JS: senza
				 * JavaScript il pannello resta aperto e utilizzabile invece
				 * di sparire per sempre dietro a un pulsante che non
				 * risponde.
				 */
				?>
				<button type="button"
					class="fsp-filters__toggle"
					data-fsp-filters-toggle
					aria-expanded="true"
					aria-controls="fsp-filters-panel">
					<span class="fsp-filters__toggle-label"><?php esc_html_e( 'Filtra', 'francystore-portfolio' ); ?></span>
					<span class="fsp-filters__badge" data-fsp-filters-badge hidden></span>
					<span class="fsp-filters__chevron" aria-hidden="true"></span>
				</button>

				<div class="fsp-filters__panel" id="fsp-filters-panel" data-fsp-filters-panel>

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

				</div>

				<?php // Fuori dal pannello: il conteggio dei pezzi serve anche a filtri chiusi. ?>
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
