<?php
/**
 * Riga di servizio in fondo alle pagine del plugin.
 *
 * I template del portfolio sono documenti HTML completi e non passano da
 * get_footer(), quindi il footer del tema non compare e i link alle
 * informative vanno riportati qui.
 *
 * Va richiesta FUORI dai contenitori con lo sfondo fotografico e subito
 * prima di wp_footer(): appoggiata sul fondo pagina non deve competere
 * con gli z-index dei livelli dello sfondo, che sono fissi e coprono lo
 * schermo.
 *
 * Non ha niente a che vedere con il banner dei cookie: quello lo inietta
 * il tema o il plugin di consenso agganciandosi a wp_footer(), che i
 * template chiamano già.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fsp_footer_privacy = FSP_Settings::get_footer_privacy_url();
$fsp_footer_cookie  = FSP_Settings::get_footer_cookie_url();
$fsp_footer_owner   = FSP_Settings::get_footer_owner();

/*
 * Le voci si accumulano in un elenco e vengono unite alla fine con il
 * separatore: costruendo la riga a pezzi, una voce non configurata si
 * porterebbe dietro il proprio "·" e resterebbero separatori orfani ai
 * bordi o doppi in mezzo.
 */
$fsp_footer_items = array();

if ( $fsp_footer_privacy && $fsp_footer_privacy === $fsp_footer_cookie ) {
	/*
	 * Stesso indirizzo per le due informative — il caso normale, perché
	 * di solito stanno nello stesso documento: un link solo che le copre
	 * entrambe, invece di due link identici uno accanto all'altro.
	 */
	$fsp_footer_items[] = '<a href="' . esc_url( $fsp_footer_privacy ) . '">'
		. esc_html__( 'Privacy Policy', 'francystore-portfolio' )
		. ' <span class="fsp-footer__sep" aria-hidden="true">&middot;</span> '
		. esc_html__( 'Cookie Policy', 'francystore-portfolio' )
		. '</a>';
} else {
	// Indirizzi diversi: due link distinti, ognuno al posto suo.
	if ( $fsp_footer_privacy ) {
		$fsp_footer_items[] = '<a href="' . esc_url( $fsp_footer_privacy ) . '">'
			. esc_html__( 'Privacy Policy', 'francystore-portfolio' ) . '</a>';
	}

	if ( $fsp_footer_cookie ) {
		$fsp_footer_items[] = '<a href="' . esc_url( $fsp_footer_cookie ) . '">'
			. esc_html__( 'Cookie Policy', 'francystore-portfolio' ) . '</a>';
	}
}

if ( $fsp_footer_owner ) {
	/*
	 * wp_date() e non date(): tiene conto del fuso orario impostato nel
	 * sito, quindi a Capodanno l'anno cambia quando cambia qui e non
	 * quando cambia a Greenwich.
	 */
	$fsp_footer_items[] = '&copy; ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( $fsp_footer_owner );
}

$fsp_footer_items[] = esc_html__( 'Tutti i diritti riservati', 'francystore-portfolio' );
?>
<footer class="fsp-footer">
	<?php
	echo implode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ogni voce è già escapata sopra, una per una.
		' <span class="fsp-footer__sep" aria-hidden="true">&middot;</span> ',
		$fsp_footer_items
	);
	?>
</footer>
