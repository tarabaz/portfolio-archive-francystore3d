<?php
/**
 * Colonne personalizzate nella lista dei pezzi in wp-admin.
 *
 * Con un archivio di un centinaio di pezzi la lista di default (solo
 * titolo e data) costringe ad aprire la scheda per capire di quale
 * oggetto si tratti: miniatura e codice in colonna risolvono a colpo
 * d'occhio.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Admin_Columns {

	/**
	 * Aggancia colonne, contenuto e ordinamento.
	 */
	public static function init() {
		add_filter( 'manage_' . FSP_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_columns' ) );
		add_action( 'manage_' . FSP_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . FSP_CPT::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'handle_sorting' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_column_styles' ) );
	}

	/**
	 * Inserisce miniatura e codice, la prima in testa e il secondo
	 * subito dopo il titolo.
	 *
	 * Si ricostruisce l'array invece di usare un semplice merge perché
	 * l'ordine delle chiavi è l'ordine delle colonne a schermo.
	 *
	 * @param array<string,string> $columns Colonne esistenti.
	 * @return array<string,string>
	 */
	public static function add_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			// La checkbox di selezione multipla resta sempre per prima.
			if ( 'cb' === $key ) {
				$new[ $key ]        = $label;
				$new['fsp_thumb']   = __( 'Copertina', 'francystore-portfolio' );
				continue;
			}

			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['fsp_codice'] = __( 'Codice', 'francystore-portfolio' );
			}
		}

		return $new;
	}

	/**
	 * Stampa il contenuto delle colonne aggiunte.
	 *
	 * @param string $column  Chiave della colonna.
	 * @param int    $post_id ID del pezzo.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'fsp_thumb' === $column ) {
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup generato dal core.
			} else {
				echo '<span class="fsp-col-empty" aria-hidden="true">—</span>';
			}

			return;
		}

		if ( 'fsp_codice' === $column ) {
			$codice = FSP_Meta::get( $post_id, 'codice' );

			echo $codice ? '<code>' . esc_html( $codice ) . '</code>' : '<span class="fsp-col-empty" aria-hidden="true">—</span>';
		}
	}

	/**
	 * Rende ordinabile la colonna del codice.
	 *
	 * @param array<string,string> $columns Colonne ordinabili.
	 * @return array<string,string>
	 */
	public static function add_sortable_columns( $columns ) {
		$columns['fsp_codice'] = 'fsp_codice';

		return $columns;
	}

	/**
	 * Applica l'ordinamento per codice pezzo.
	 *
	 * Si usa 'meta_value' e non 'meta_value_num': i codici sono stringhe
	 * tipo "FS-042", e un ordinamento numerico li leggerebbe tutti come
	 * zero, lasciando la lista nell'ordine di inserimento.
	 *
	 * @param WP_Query $query Query in corso.
	 */
	public static function handle_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'fsp_codice' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', FSP_Meta::PREFIX . 'codice' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query->set( 'orderby', 'meta_value' );
	}

	/**
	 * Larghezze delle colonne aggiunte.
	 *
	 * Poche righe di CSS inline invece di un file dedicato: caricarne
	 * uno intero per due regole valide solo in questa schermata
	 * costerebbe una richiesta HTTP in più ad ogni apertura della lista.
	 */
	public static function print_column_styles() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . FSP_CPT::POST_TYPE !== $screen->id ) {
			return;
		}
		?>
		<style>
			.column-fsp_thumb { width: 74px; }
			.column-fsp_thumb img { display: block; width: 60px; height: 60px; object-fit: cover; border-radius: 3px; }
			.column-fsp_codice { width: 110px; }
			.fsp-col-empty { color: #b0b5ba; }
		</style>
		<?php
	}
}
