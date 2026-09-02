<?php
/**
 * Campi meta del pezzo: dati base, attributi liberi e galleria.
 *
 * Centralizza chiavi, sanitizzazione e helper di lettura, usati sia dal
 * meta box in wp-admin sia dai template di frontend.
 *
 * @package FrancyStorePortfolio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FSP_Meta {

	/** Prefisso comune a tutte le chiavi meta del plugin. */
	const PREFIX = 'fsp_';

	/** Meta con la lista di coppie chiave/valore libere. */
	const KEY_ATTRIBUTES = 'fsp_attributi';

	/** Meta con gli ID degli allegati della galleria. */
	const KEY_GALLERY = 'fsp_galleria';

	/** Meta con l'ID dell'immagine di sfondo dedicata al pezzo. */
	const KEY_BACKGROUND = 'fsp_sfondo';

	/**
	 * Campi base, sempre presenti su ogni pezzo.
	 *
	 * Sono volutamente pochi: tutto ciò che vale solo per alcuni tipi di
	 * pezzo (alimentazione e tipo di illuminazione per una lampada,
	 * scala per una figure) sta negli attributi liberi, che non
	 * costringono a portarsi dietro campi vuoti su tutto il resto
	 * dell'archivio.
	 *
	 * Chiave meta senza prefisso => etichetta mostrata.
	 *
	 * @return array<string,string>
	 */
	public static function get_base_fields() {
		return array(
			'codice'    => __( 'Codice pezzo', 'francystore-portfolio' ),
			'materiale' => __( 'Materiale', 'francystore-portfolio' ),
			'altezza'   => __( 'Altezza', 'francystore-portfolio' ),
			'tempo'     => __( 'Tempo di realizzazione', 'francystore-portfolio' ),
			'anno'      => __( 'Anno', 'francystore-portfolio' ),
		);
	}

	/**
	 * Aggancia la registrazione dei meta.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta_fields' ) );
	}

	/**
	 * Registra tutti i meta del CPT.
	 */
	public static function register_meta_fields() {
		foreach ( array_keys( self::get_base_fields() ) as $key ) {
			register_post_meta(
				FSP_CPT::POST_TYPE,
				self::PREFIX . $key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'auth_callback' ),
				)
			);
		}

		/*
		 * Attributi e galleria restano fuori dalla REST API
		 * (show_in_rest => false): esporre un array di forma libera
		 * richiede di dichiararne lo schema completo, e non serve a
		 * nulla qui perché entrambi si compilano dal meta box classico,
		 * non dall'editor a blocchi.
		 */
		register_post_meta(
			FSP_CPT::POST_TYPE,
			self::KEY_ATTRIBUTES,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_attributes' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			FSP_CPT::POST_TYPE,
			self::KEY_GALLERY,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_gallery' ),
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);

		register_post_meta(
			FSP_CPT::POST_TYPE,
			self::KEY_BACKGROUND,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
				'auth_callback'     => array( __CLASS__, 'auth_callback' ),
			)
		);
	}

	/**
	 * Immagine di sfondo da usare dietro alla scheda del pezzo.
	 *
	 * Si scende per gradi: sfondo del singolo pezzo, poi quello della
	 * sua prima sezione, poi quello generale del portfolio. Così basta
	 * impostare un'immagine per sezione per avere tutto coerente, e si
	 * scende al singolo pezzo solo quando ne vale la pena — senza dover
	 * ricaricare uno sfondo su ogni scheda.
	 *
	 * @param int $post_id ID del pezzo.
	 * @return int ID allegato, 0 se non c'è nessuno sfondo a nessun livello.
	 */
	public static function get_background_id( $post_id ) {
		$own = (int) get_post_meta( $post_id, self::KEY_BACKGROUND, true );

		if ( $own ) {
			return $own;
		}

		$sections = get_the_terms( $post_id, FSP_Taxonomies::SECTION );

		if ( ! is_wp_error( $sections ) && $sections ) {
			foreach ( $sections as $section ) {
				$section_background = FSP_Taxonomies::get_background_id( $section->term_id );

				if ( $section_background ) {
					return $section_background;
				}
			}
		}

		return FSP_Settings::get_home_background_id();
	}

	/**
	 * Immagine grande della scheda: la copertina del pezzo, oppure la
	 * prima della galleria quando la copertina non è stata impostata.
	 *
	 * Il ripiego evita che una scheda con dieci foto caricate resti
	 * senza immagine principale solo perché ci si è dimenticati di
	 * indicare quale fosse la copertina.
	 *
	 * @param int $post_id ID del pezzo.
	 * @return int ID allegato, 0 se il pezzo non ha nessuna immagine.
	 */
	public static function get_main_image_id( $post_id ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $post_id );

		if ( $thumbnail_id ) {
			return $thumbnail_id;
		}

		$gallery = self::get_gallery( $post_id );

		return $gallery ? (int) $gallery[0] : 0;
	}

	/**
	 * Normalizza la lista di attributi liberi.
	 *
	 * Scarta le righe senza etichetta: una riga con solo il valore
	 * comparirebbe nella scheda come un dato senza nome, e quasi sempre
	 * è il residuo di una riga aggiunta e poi lasciata a metà.
	 *
	 * @param mixed $value Valore grezzo dal form.
	 * @return array<int,array{label:string,value:string}>
	 */
	public static function sanitize_attributes( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$val   = isset( $row['value'] ) ? sanitize_text_field( $row['value'] ) : '';

			if ( '' === trim( $label ) ) {
				continue;
			}

			$clean[] = array(
				'label' => $label,
				'value' => $val,
			);
		}

		return $clean;
	}

	/**
	 * Normalizza la galleria in una lista di ID allegato validi.
	 *
	 * @param mixed $value Valore grezzo (array o stringa di ID separati da virgola).
	 * @return int[]
	 */
	public static function sanitize_gallery( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_map( 'absint', $value );
		$ids = array_filter( $ids );

		// array_values() perché array_filter/array_unique lasciano buchi
		// negli indici, e un array con chiavi non consecutive viene
		// serializzato come oggetto invece che come lista.
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Autorizzazione lettura/scrittura per register_post_meta().
	 *
	 * @return bool
	 */
	public static function auth_callback() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Legge un campo base del pezzo, applicando il prefisso.
	 *
	 * @param int    $post_id ID del pezzo.
	 * @param string $key     Nome del campo senza prefisso (es. "materiale").
	 * @return string
	 */
	public static function get( $post_id, $key ) {
		return (string) get_post_meta( $post_id, self::PREFIX . $key, true );
	}

	/**
	 * Attributi liberi del pezzo, già normalizzati.
	 *
	 * @param int $post_id ID del pezzo.
	 * @return array<int,array{label:string,value:string}>
	 */
	public static function get_attributes( $post_id ) {
		$value = get_post_meta( $post_id, self::KEY_ATTRIBUTES, true );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * ID degli allegati della galleria.
	 *
	 * @param int $post_id ID del pezzo.
	 * @return int[]
	 */
	public static function get_gallery( $post_id ) {
		$value = get_post_meta( $post_id, self::KEY_GALLERY, true );

		return is_array( $value ) ? array_map( 'absint', $value ) : array();
	}

	/**
	 * Righe della scheda dati: campi base compilati + attributi liberi,
	 * nell'ordine in cui vanno mostrate nel pannello della scheda.
	 *
	 * I campi vuoti non entrano nell'elenco: la scheda mostra solo
	 * quello che hai effettivamente compilato, senza righe "—".
	 *
	 * @param int $post_id ID del pezzo.
	 * @return array<int,array{label:string,value:string}>
	 */
	public static function get_spec_rows( $post_id ) {
		$rows = array();

		foreach ( self::get_base_fields() as $key => $label ) {
			// Il codice pezzo ha già una riga dedicata in cima alla
			// scheda: ripeterlo qui sarebbe ridondante.
			if ( 'codice' === $key ) {
				continue;
			}

			$value = self::get( $post_id, $key );

			if ( '' === trim( $value ) ) {
				continue;
			}

			$rows[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		foreach ( self::get_attributes( $post_id ) as $row ) {
			if ( '' === trim( (string) $row['value'] ) ) {
				continue;
			}

			$rows[] = array(
				'label' => (string) $row['label'],
				'value' => (string) $row['value'],
			);
		}

		return $rows;
	}
}
