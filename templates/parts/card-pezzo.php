<?php
/**
 * Scheda di un pezzo nella griglia del portfolio.
 *
 * Va richiesto dentro un loop attivo (the_post() già chiamato): usa i
 * dati del post corrente.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fsp_id       = get_the_ID();
$fsp_codice   = FSP_Meta::get( $fsp_id, 'codice' );
$fsp_sections = get_the_terms( $fsp_id, FSP_Taxonomies::SECTION );
$fsp_types    = get_the_terms( $fsp_id, FSP_Taxonomies::TAG );

$fsp_sections = is_wp_error( $fsp_sections ) || ! $fsp_sections ? array() : $fsp_sections;
$fsp_types    = is_wp_error( $fsp_types ) || ! $fsp_types ? array() : $fsp_types;

/*
 * Sezioni e tipologie finiscono in due attributi data come slug separati
 * da spazio: il filtro JS li legge da qui, quindi non deve interrogare
 * il server ad ogni click. Lo spazio in testa e in coda serve al confronto
 * con includes(' slug '), che così non fa scattare "lampada" su
 * "lampada-grande".
 */
$fsp_section_slugs = ' ' . implode( ' ', wp_list_pluck( $fsp_sections, 'slug' ) ) . ' ';
$fsp_type_slugs    = ' ' . implode( ' ', wp_list_pluck( $fsp_types, 'slug' ) ) . ' ';

// Etichetta mostrata sulla scheda: la prima sezione assegnata.
$fsp_section_label = $fsp_sections ? $fsp_sections[0]->name : '';
?>
<article class="fsp-card"
	data-fsp-card
	data-sections="<?php echo esc_attr( $fsp_section_slugs ); ?>"
	data-types="<?php echo esc_attr( $fsp_type_slugs ); ?>">

	<a class="fsp-card__link" href="<?php the_permalink(); ?>">

		<div class="fsp-card__media">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php
				the_post_thumbnail(
					'large',
					array(
						// Le schede fuori schermo si caricano solo quando servono:
						// con un centinaio di pezzi in pagina unica è ciò che tiene
						// leggero il primo caricamento.
						'loading' => 'lazy',
						'alt'     => the_title_attribute( array( 'echo' => false ) ),
					)
				);
				?>
			<?php else : ?>
				<div class="fsp-card__placeholder" aria-hidden="true"></div>
			<?php endif; ?>
		</div>

		<span class="fsp-card__corner fsp-card__corner--tl" aria-hidden="true"></span>
		<span class="fsp-card__corner fsp-card__corner--tr" aria-hidden="true"></span>
		<span class="fsp-card__corner fsp-card__corner--bl" aria-hidden="true"></span>
		<span class="fsp-card__corner fsp-card__corner--br" aria-hidden="true"></span>

		<div class="fsp-card__body">
			<?php if ( $fsp_codice ) : ?>
				<div class="fsp-card__code"><?php echo esc_html( $fsp_codice ); ?></div>
			<?php endif; ?>

			<h2 class="fsp-card__title fsp-display"><?php the_title(); ?></h2>

			<?php if ( $fsp_section_label ) : ?>
				<div class="fsp-card__section"><?php echo esc_html( $fsp_section_label ); ?></div>
			<?php endif; ?>
		</div>

	</a>
</article>
