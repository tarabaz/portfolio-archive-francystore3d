/**
 * Backend del plugin: selettore immagine, galleria e tabella degli
 * attributi liberi.
 *
 * Usa wp.media, già caricato da wp_enqueue_media(). Niente jQuery: le
 * poche interazioni qui dentro non lo richiedono.
 */
( function () {
	'use strict';

	var l10n = window.fspAdmin || {};

	/* ------------------------------------------------------------------
	 * Selettore di una singola immagine
	 * ------------------------------------------------------------------ */

	function initMediaPickers() {
		var pickers = document.querySelectorAll( '[data-fsp-media]' );

		Array.prototype.forEach.call( pickers, function ( picker ) {
			var input = picker.querySelector( '[data-fsp-media-input]' );
			var preview = picker.querySelector( '[data-fsp-media-preview]' );
			var selectBtn = picker.querySelector( '[data-fsp-media-select]' );
			var removeBtn = picker.querySelector( '[data-fsp-media-remove]' );
			var frame = null;

			if ( ! input || ! selectBtn ) {
				return;
			}

			selectBtn.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				// Il frame si crea una volta sola e si riusa: ricrearlo ad
				// ogni click lascerebbe in memoria una finestra per click.
				if ( ! frame ) {
					frame = wp.media( {
						title: l10n.mediaTitle || '',
						button: { text: l10n.mediaButton || '' },
						library: { type: 'image' },
						multiple: false
					} );

					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();

						input.value = attachment.id;
						renderPreview( attachment );

						if ( removeBtn ) {
							removeBtn.hidden = false;
						}
					} );
				}

				frame.open();
			} );

			if ( removeBtn ) {
				removeBtn.addEventListener( 'click', function ( event ) {
					event.preventDefault();

					input.value = '';

					if ( preview ) {
						preview.innerHTML = '';
					}

					removeBtn.hidden = true;
				} );
			}

			function renderPreview( attachment ) {
				if ( ! preview ) {
					return;
				}

				preview.innerHTML = '';

				var img = document.createElement( 'img' );

				// Si preferisce la miniatura media quando esiste: caricare
				// l'originale a piena risoluzione per un riquadro di
				// anteprima è sprecato.
				img.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				img.alt = '';

				preview.appendChild( img );
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Galleria a più immagini
	 * ------------------------------------------------------------------ */

	function initGallery() {
		var gallery = document.querySelector( '[data-fsp-gallery]' );

		if ( ! gallery ) {
			return;
		}

		var list = gallery.querySelector( '[data-fsp-gallery-list]' );
		var input = gallery.querySelector( '[data-fsp-gallery-input]' );
		var selectBtn = gallery.querySelector( '[data-fsp-gallery-select]' );
		var frame = null;

		if ( ! list || ! input || ! selectBtn ) {
			return;
		}

		selectBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			/*
			 * Il frame va ricreato ad ogni apertura, a differenza del
			 * selettore singolo: la selezione di partenza sono le immagini
			 * già presenti, che cambiano dopo ogni salvataggio. Riusandolo
			 * si riaprirebbe sempre con la selezione della prima volta.
			 */
			frame = wp.media( {
				title: l10n.galleryTitle || '',
				button: { text: l10n.galleryButton || '' },
				library: { type: 'image' },
				multiple: 'add'
			} );

			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );

				currentIds().forEach( function ( id ) {
					var attachment = wp.media.attachment( id );

					attachment.fetch();
					selection.add( attachment );
				} );
			} );

			frame.on( 'select', function () {
				var attachments = frame.state().get( 'selection' ).toJSON();

				list.innerHTML = '';

				attachments.forEach( function ( attachment ) {
					var item = document.createElement( 'li' );

					item.className = 'fsp-gallery__item';
					item.setAttribute( 'data-fsp-gallery-item', '' );
					item.setAttribute( 'data-id', attachment.id );

					var img = document.createElement( 'img' );

					img.src = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
					img.alt = '';

					item.appendChild( img );
					list.appendChild( item );
				} );

				syncInput();
			} );

			frame.open();
		} );

		function currentIds() {
			return Array.prototype.map.call(
				list.querySelectorAll( '[data-fsp-gallery-item]' ),
				function ( item ) {
					return parseInt( item.getAttribute( 'data-id' ), 10 );
				}
			).filter( Boolean );
		}

		function syncInput() {
			input.value = currentIds().join( ',' );
		}
	}

	/* ------------------------------------------------------------------
	 * Tabella degli attributi liberi
	 * ------------------------------------------------------------------ */

	function initAttributes() {
		var table = document.querySelector( '[data-fsp-attributes]' );

		if ( ! table ) {
			return;
		}

		var body = table.querySelector( '[data-fsp-attributes-body]' );
		var template = document.querySelector( '[data-fsp-attributes-template]' );
		var addBtn = document.querySelector( '[data-fsp-attributes-add]' );

		if ( ! body || ! template || ! addBtn ) {
			return;
		}

		addBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			var clone = template.content.cloneNode( true );

			body.appendChild( clone );
			reindex();

			// Il cursore va subito nel campo etichetta della riga appena
			// creata: aggiungere una riga serve a scriverci dentro.
			var rows = body.querySelectorAll( '[data-fsp-attributes-row]' );
			var lastInput = rows[ rows.length - 1 ].querySelector( 'input' );

			if ( lastInput ) {
				lastInput.focus();
			}
		} );

		body.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-fsp-attributes-remove]' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			var row = button.closest( '[data-fsp-attributes-row]' );

			if ( ! row ) {
				return;
			}

			// Si chiede conferma solo se la riga ha qualcosa dentro:
			// chiederla su una riga vuota appena aggiunta è solo un
			// intralcio.
			if ( hasContent( row ) && ! window.confirm( l10n.confirmRemoveRow || '' ) ) {
				return;
			}

			row.remove();
			reindex();
		} );

		function hasContent( row ) {
			return Array.prototype.some.call( row.querySelectorAll( 'input' ), function ( input ) {
				return input.value.trim() !== '';
			} );
		}

		/*
		 * Rinumera i name dei campi secondo la posizione a schermo.
		 *
		 * PHP riceve gli attributi come array indicizzato e ne conserva
		 * l'ordine: senza rinumerare, cancellare una riga in mezzo
		 * lascerebbe un buco negli indici e l'ordine finale seguirebbe i
		 * numeri vecchi invece di quello che si vede nella tabella.
		 */
		function reindex() {
			var rows = body.querySelectorAll( '[data-fsp-attributes-row]' );

			Array.prototype.forEach.call( rows, function ( row, index ) {
				Array.prototype.forEach.call( row.querySelectorAll( 'input' ), function ( input ) {
					var name = input.getAttribute( 'name' );

					if ( ! name ) {
						return;
					}

					input.setAttribute( 'name', name.replace( /\[(?:__INDEX__|\d+)\]/, '[' + index + ']' ) );
				} );
			} );
		}

		initRowDragging( body, reindex );
	}

	/**
	 * Trascinamento delle righe per riordinarle.
	 *
	 * Drag and drop nativo invece di jQuery UI Sortable: sono una decina
	 * di righe e non aggiunge dipendenze da caricare in ogni schermata di
	 * modifica.
	 *
	 * @param {Element}  body    Corpo della tabella.
	 * @param {Function} onDrop  Callback dopo lo spostamento.
	 */
	function initRowDragging( body, onDrop ) {
		var dragged = null;

		body.addEventListener( 'mousedown', function ( event ) {
			var grip = event.target.closest( '.fsp-attributes__grip' );
			var row = event.target.closest( '[data-fsp-attributes-row]' );

			if ( ! row ) {
				return;
			}

			// Solo la maniglia avvia il trascinamento: se fosse tutta la
			// riga, selezionare del testo dentro un campo la sposterebbe.
			row.draggable = !! grip;
		} );

		body.addEventListener( 'dragstart', function ( event ) {
			dragged = event.target.closest( '[data-fsp-attributes-row]' );

			if ( ! dragged ) {
				return;
			}

			dragged.classList.add( 'is-dragging' );
			event.dataTransfer.effectAllowed = 'move';

			// Firefox non avvia il trascinamento senza dati impostati.
			event.dataTransfer.setData( 'text/plain', '' );
		} );

		body.addEventListener( 'dragover', function ( event ) {
			if ( ! dragged ) {
				return;
			}

			event.preventDefault();

			var target = event.target.closest( '[data-fsp-attributes-row]' );

			if ( ! target || target === dragged ) {
				return;
			}

			var rect = target.getBoundingClientRect();
			var isAfter = ( event.clientY - rect.top ) > ( rect.height / 2 );

			body.insertBefore( dragged, isAfter ? target.nextSibling : target );
		} );

		body.addEventListener( 'dragend', function () {
			if ( ! dragged ) {
				return;
			}

			dragged.classList.remove( 'is-dragging' );
			dragged.draggable = false;
			dragged = null;

			onDrop();
		} );
	}

	/* ------------------------------------------------------------------
	 * Cursori di regolazione del fumo
	 * ------------------------------------------------------------------ */

	/**
	 * Tiene allineati il cursore e la casella numerica che gli sta
	 * accanto.
	 *
	 * Servono tutti e due: il cursore per cercare il valore a occhio
	 * trascinando, la casella per rimettere esattamente il numero che si
	 * era trovato buono la volta prima. A salvare è sempre la casella,
	 * che è l'unica ad avere un name.
	 */
	function initRanges() {
		var ranges = document.querySelectorAll( '[data-fsp-range]' );

		Array.prototype.forEach.call( ranges, function ( range ) {
			var key = range.getAttribute( 'data-fsp-range' );
			var field = document.querySelector( '[data-fsp-range-value="' + key + '"]' );

			if ( ! field ) {
				return;
			}

			range.addEventListener( 'input', function () {
				field.value = range.value;
			} );

			field.addEventListener( 'input', function () {
				var value = parseInt( field.value, 10 );

				if ( isNaN( value ) ) {
					return;
				}

				range.value = Math.max( 0, Math.min( 100, value ) );
			} );

			// Il rientro nei limiti si fa all'uscita dal campo e non mentre
			// si digita: correggendo a ogni tasto, chi scrive "100"
			// partendo da "1" se lo vedrebbe cambiare sotto le dita.
			field.addEventListener( 'blur', function () {
				var value = parseInt( field.value, 10 );

				field.value = isNaN( value ) ? range.value : Math.max( 0, Math.min( 100, value ) );
				range.value = field.value;
			} );
		} );
	}

	function init() {
		initMediaPickers();
		initGallery();
		initAttributes();
		initRanges();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
