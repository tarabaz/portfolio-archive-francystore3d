<?php
/**
 * Plugin Name:       FrancyStore Portfolio
 * Plugin URI:        https://francystore3d.com
 * Description:       Portfolio visivo dei pezzi realizzati da FrancyStore3D (action figure, lampade, diorami, stand). Archivio filtrabile in tempo reale, indipendente dal tema. Nessun e-commerce: il contatto avviene su Instagram.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            FrancyStore3D
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       francystore-portfolio
 * Domain Path:       /languages
 *
 * @package FrancyStorePortfolio
 */

// Evita accesso diretto al file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versione del plugin. Bump manuale ad ogni modifica (anche minima):
 * usata per il cache-busting di CSS/JS negli enqueue e mostrata in
 * fondo alla pagina impostazioni, per verificare a colpo d'occhio che
 * un aggiornamento sia stato effettivamente caricato dal browser.
 */
define( 'FSP_VERSION', '1.2.1' );

/** Percorso assoluto della cartella del plugin, con trailing slash. */
define( 'FSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** URL della cartella del plugin, con trailing slash. */
define( 'FSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Percorso assoluto del file principale del plugin. */
define( 'FSP_PLUGIN_FILE', __FILE__ );

/*
 * Ogni file in includes/ definisce una classe FSP_* responsabile di
 * un'unica area funzionale e si auto-registra sui propri hook dal
 * metodo init(). L'ordine di require non conta (nessuna classe usa
 * un'altra a tempo di caricamento), ma si tiene comunque coerente con
 * l'ordine di inizializzazione qui sotto.
 */
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-cpt.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-taxonomies.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-meta.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-metabox.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-admin-columns.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-settings.php';
require_once FSP_PLUGIN_DIR . 'includes/class-fsp-template-loader.php';

/**
 * Inizializza tutte le componenti del plugin.
 */
function fsp_init_plugin() {
	FSP_CPT::init();
	FSP_Taxonomies::init();
	FSP_Meta::init();
	FSP_Metabox::init();
	FSP_Admin_Columns::init();
	FSP_Settings::init();
	FSP_Template_Loader::init();
}
add_action( 'plugins_loaded', 'fsp_init_plugin' );

/**
 * Carica le traduzioni da /languages.
 *
 * L'interfaccia è già scritta in italiano nel codice: i file .po/.mo
 * servono solo a chi volesse tradurla altrove, e a permettere di
 * ritoccare le stringhe senza modificare i sorgenti.
 */
function fsp_load_textdomain() {
	load_plugin_textdomain( 'francystore-portfolio', false, dirname( plugin_basename( FSP_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'fsp_load_textdomain' );

/**
 * Attivazione: registra CPT e tassonomie, poi rigenera le rewrite
 * rules così lo slug pubblico funziona subito, senza dover risalvare
 * a mano i permalink da wp-admin.
 */
function fsp_activate_plugin() {
	FSP_CPT::register_post_type();
	FSP_Taxonomies::register_taxonomies();
	flush_rewrite_rules();
	update_option( 'fsp_flushed_version', FSP_VERSION );
}
register_activation_hook( FSP_PLUGIN_FILE, 'fsp_activate_plugin' );

/**
 * Rigenera le rewrite rules quando la versione del plugin cambia.
 *
 * Aggiornare il plugin sostituendo i file non fa scattare l'hook di
 * attivazione: se una nuova versione cambia uno slug — com'è successo
 * passando da "tag" a "tipologia" — le vecchie regole restano in
 * circolo e gli indirizzi nuovi rispondono 404, con l'unica via
 * d'uscita di aprire Impostazioni > Permalink e premere Salva. Il
 * confronto con la versione salvata evita di dover ricordare quel
 * passaggio ad ogni aggiornamento.
 *
 * Il flush è costoso, ma qui gira una volta sola per versione: appena
 * fatto, il numero salvato coincide e le richieste successive escono
 * subito.
 */
function fsp_maybe_flush_rewrite_rules() {
	if ( get_option( 'fsp_flushed_version' ) === FSP_VERSION ) {
		return;
	}

	flush_rewrite_rules();
	update_option( 'fsp_flushed_version', FSP_VERSION );
}
add_action( 'init', 'fsp_maybe_flush_rewrite_rules', 99 );

/**
 * Disattivazione: rimuove le rewrite rules aggiunte dal CPT.
 * Non tocca i contenuti: disattivare non è disinstallare.
 */
function fsp_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( FSP_PLUGIN_FILE, 'fsp_deactivate_plugin' );
