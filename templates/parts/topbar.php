<?php
/**
 * Barra di navigazione dei template full-bleed.
 *
 * Il portfolio non carica header e footer del tema, quindi senza questi
 * pulsanti chi arriva da Google o da un link Instagram non avrebbe
 * nessun modo di raggiungere il resto del sito se non con il tasto
 * "indietro" del browser.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fsp_archive_link   = get_post_type_archive_link( FSP_CPT::POST_TYPE );
$fsp_on_archive     = FSP_Template_Loader::is_portfolio_archive();
$fsp_instagram_url  = FSP_Settings::get_instagram_url();
?>
<nav class="fsp-topbar" aria-label="<?php esc_attr_e( 'Navigazione portfolio', 'francystore-portfolio' ); ?>">
	<?php
	/*
	 * "Torna al sito" solo sulla griglia. Dalla scheda di un pezzo il
	 * passo indietro naturale è il portfolio, non la home: offrire
	 * entrambe le uscite inviterebbe ad abbandonare la scheda proprio
	 * dove invece si vuole che il visitatore legga e poi scriva.
	 */
	?>
	<?php if ( $fsp_on_archive ) : ?>
		<a class="fsp-topbar__link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo esc_html__( '← Torna al sito', 'francystore-portfolio' ); ?>
		</a>
	<?php elseif ( $fsp_archive_link ) : ?>
		<a class="fsp-topbar__link" href="<?php echo esc_url( $fsp_archive_link ); ?>">
			<?php echo esc_html__( '← Portfolio', 'francystore-portfolio' ); ?>
		</a>
	<?php endif; ?>

	<?php if ( $fsp_instagram_url ) : ?>
		<a class="fsp-topbar__link fsp-topbar__link--ig" href="<?php echo esc_url( $fsp_instagram_url ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Instagram', 'francystore-portfolio' ); ?>
		</a>
	<?php endif; ?>
</nav>
