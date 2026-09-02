/**
 * Frontend del portfolio: filtri in tempo reale con riordino animato,
 * cambio dello sfondo per sezione, ingrandimento foto e copia del
 * codice pezzo.
 *
 * Vanilla, senza dipendenze: il riordino animato è l'unica cosa per cui
 * di solito si tira dentro una libreria (Isotope, Shuffle, Muuri), ma
 * la tecnica che usano tutte — FLIP — sta in poche decine di righe e
 * non vale trenta e passa KB di JavaScript su ogni caricamento.
 */
( function () {
	'use strict';

	var l10n = window.fspL10n || {};

	/** Durata dell'animazione di riordino, in millisecondi. */
	var FLIP_DURATION = 420;

	/**
	 * L'utente ha chiesto al sistema operativo di ridurre le animazioni:
	 * in quel caso i filtri restano istantanei. Si legge ad ogni uso e
	 * non una volta sola perché l'impostazione può cambiare a pagina
	 * aperta.
	 *
	 * @return {boolean}
	 */
	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	/* ------------------------------------------------------------------
	 * Filtri della griglia
	 * ------------------------------------------------------------------ */

	function initFilters() {
		var root = document.querySelector( '[data-fsp-archive]' );
		var grid = document.querySelector( '[data-fsp-grid]' );

		if ( ! root || ! grid ) {
			return;
		}

		var cards = Array.prototype.slice.call( grid.querySelectorAll( '[data-fsp-card]' ) );
		var chips = Array.prototype.slice.call( document.querySelectorAll( '[data-fsp-filter]' ) );
		var countEl = document.querySelector( '[data-fsp-count]' );
		var resetEl = document.querySelector( '[data-fsp-reset]' );
		var emptyEl = document.querySelector( '[data-fsp-empty]' );

		if ( ! cards.length ) {
			return;
		}

		var backgrounds = parseJSON( root.getAttribute( 'data-section-backgrounds' ) ) || {};
		var homeBackground = root.getAttribute( 'data-home-background' ) || '';

		var toggleEl = document.querySelector( '[data-fsp-filters-toggle]' );
		var panelEl = document.querySelector( '[data-fsp-filters-panel]' );
		var badgeEl = document.querySelector( '[data-fsp-filters-badge]' );

		// Vero solo fino al primo apply(), per non animare il caricamento.
		var isFirstRun = true;

		// Stato dei filtri: due insiemi di slug selezionati.
		var selected = {
			section: [],
			type: []
		};

		/**
		 * Un pezzo passa il filtro se rientra in ALMENO UNA delle sezioni
		 * selezionate E in ALMENO UNA delle tipologie selezionate. I due
		 * gruppi si restringono a vicenda, le voci dentro un gruppo si
		 * sommano: spuntare "Lampade" e "Diorami" mostra entrambe le
		 * famiglie, aggiungere la tipologia "Anime" tiene di quelle solo
		 * le a tema anime. Un gruppo senza selezioni non filtra nulla.
		 *
		 * @param {Element} card Scheda da valutare.
		 * @return {boolean}
		 */
		function matches( card ) {
			return groupMatches( card.getAttribute( 'data-sections' ), selected.section ) &&
				groupMatches( card.getAttribute( 'data-types' ), selected.type );
		}

		/**
		 * @param {string} haystack Slug della scheda, separati da spazio e con spazi ai bordi.
		 * @param {Array}  needles  Slug selezionati nel gruppo.
		 * @return {boolean}
		 */
		function groupMatches( haystack, needles ) {
			if ( ! needles.length ) {
				return true;
			}

			var value = haystack || '';

			return needles.some( function ( slug ) {
				// Gli spazi ai bordi evitano che "lampada" corrisponda a
				// "lampada-grande": si confronta lo slug intero.
				return value.indexOf( ' ' + slug + ' ' ) !== -1;
			} );
		}

		/**
		 * Applica i filtri correnti animando lo spostamento delle schede
		 * con la tecnica FLIP: si misura dove sono (First), si cambia il
		 * layout (Last), si rimettono con una trasformazione dove erano
		 * (Invert) e si lascia che la transizione le riporti al loro
		 * nuovo posto (Play). Il browser anima quindi solo transform, che
		 * non ricalcola il layout ad ogni fotogramma.
		 */
		function apply() {
			// Al primo giro la griglia si disegna già filtrata: animare
			// significherebbe far volare le schede appena la pagina compare.
			var animate = ! prefersReducedMotion() && ! isFirstRun;
			var first = {};

			isFirstRun = false;

			if ( animate ) {
				cards.forEach( function ( card, index ) {
					if ( ! card.hidden ) {
						first[ index ] = card.getBoundingClientRect();
					}
				} );
			}

			var visible = 0;
			var entering = [];

			cards.forEach( function ( card, index ) {
				var show = matches( card );
				var wasVisible = ! card.hidden;

				card.hidden = ! show;

				if ( ! show ) {
					return;
				}

				visible++;

				if ( ! wasVisible ) {
					entering.push( card );
				}
			} );

			updateStatus( visible );

			if ( ! animate ) {
				return;
			}

			cards.forEach( function ( card, index ) {
				if ( card.hidden || ! first[ index ] ) {
					return;
				}

				var last = card.getBoundingClientRect();
				var dx = first[ index ].left - last.left;
				var dy = first[ index ].top - last.top;

				// Scheda che non si è mossa: niente animazione, così non
				// si paga una transizione per ogni card della griglia.
				if ( ! dx && ! dy ) {
					return;
				}

				card.style.transition = 'none';
				card.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';

				// Doppio requestAnimationFrame: il primo fotogramma serve al
				// browser per registrare la posizione invertita. Togliendo la
				// trasformazione nello stesso frame in cui la si imposta, non
				// ci sarebbe nessuno stato di partenza da cui animare.
				requestAnimationFrame( function () {
					requestAnimationFrame( function () {
						card.style.transition = 'transform ' + FLIP_DURATION + 'ms cubic-bezier(.22,.61,.36,1)';
						card.style.transform = '';
					} );
				} );
			} );

			entering.forEach( function ( card ) {
				card.classList.remove( 'fsp-card--in' );

				// Riavvia l'animazione di entrata anche quando la scheda
				// l'aveva già fatta poco prima: leggere offsetWidth forza il
				// browser a considerare chiusa quella precedente.
				void card.offsetWidth;
				card.classList.add( 'fsp-card--in' );
			} );
		}

		/**
		 * Aggiorna contatore, stato vuoto, pulsante di azzeramento e
		 * sfondo.
		 *
		 * @param {number} visible Numero di schede mostrate.
		 */
		function updateStatus( visible ) {
			if ( countEl ) {
				var template = visible === 1 ? ( l10n.countOne || '%d pezzo' ) : ( l10n.countMany || '%d pezzi' );
				countEl.textContent = template.replace( '%d', visible );
			}

			if ( emptyEl ) {
				emptyEl.hidden = visible > 0;
			}

			var active = selected.section.length + selected.type.length;
			var hasFilters = active > 0;

			if ( resetEl ) {
				resetEl.hidden = ! hasFilters;
			}

			// A pannello chiuso il numero sul pulsante è l'unico indizio che
			// la griglia non sta mostrando tutto.
			if ( badgeEl ) {
				badgeEl.textContent = active;
				badgeEl.hidden = ! hasFilters;
			}

			updateBackground();
			updateUrl();
		}

		/**
		 * Sfondo della griglia.
		 *
		 * Con una sola sezione selezionata si mostra il suo sfondo; con
		 * nessuna o più di una si torna a quello generale. Tenere il
		 * primo della lista quando ce ne sono due significherebbe far
		 * cambiare l'ambientazione in base all'ordine in cui si sono
		 * spuntati i filtri, che dà l'impressione di un comportamento
		 * casuale.
		 */
		function updateBackground() {
			var target = homeBackground;

			if ( selected.section.length === 1 ) {
				var candidate = backgrounds[ selected.section[ 0 ] ];

				// Sezione senza sfondo proprio: si resta su quello generale
				// invece di lasciare la griglia sul nero.
				if ( candidate ) {
					target = candidate;
				}
			}

			swapBackground( target );
		}

		/**
		 * Sostituisce l'immagine di sfondo con una dissolvenza.
		 *
		 * @param {string} url Nuova immagine ('' per nessuna).
		 */
		function swapBackground( url ) {
			var holder = document.querySelector( '[data-fsp-bg]' );

			if ( ! holder ) {
				return;
			}

			var current = holder.querySelector( '[data-fsp-bg-image]' );
			var currentUrl = current ? current.getAttribute( 'src' ) : '';

			if ( currentUrl === url ) {
				return;
			}

			if ( ! url ) {
				if ( current ) {
					current.remove();
				}

				return;
			}

			var next = new Image();
			next.setAttribute( 'data-fsp-bg-image', '' );
			next.className = 'fsp-archive__bg-image--in';
			next.alt = '';

			/*
			 * Si inserisce solo a caricamento avvenuto: aggiungendola
			 * subito, la dissolvenza partirebbe su un'immagine ancora
			 * vuota e si vedrebbe un lampo di sfondo nudo.
			 */
			next.onload = function () {
				holder.appendChild( next );

				requestAnimationFrame( function () {
					next.classList.remove( 'fsp-archive__bg-image--in' );

					if ( current ) {
						current.classList.add( 'fsp-archive__bg-image--out' );
						window.setTimeout( function () {
							current.remove();
						}, 500 );
					}
				} );
			};

			next.src = url;
		}

		/**
		 * Riporta i filtri attivi nella barra degli indirizzi, così la
		 * vista filtrata si può linkare e sopravvive a un ricaricamento.
		 * replaceState e non pushState: ogni click aggiungerebbe una voce
		 * nella cronologia, e per tornare al sito servirebbero dieci
		 * "indietro".
		 */
		function updateUrl() {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}

			var params = new URLSearchParams( window.location.search );

			setParam( params, 'sezione', selected.section );
			setParam( params, 'tipologia', selected.type );

			var query = params.toString();

			window.history.replaceState( null, '', window.location.pathname + ( query ? '?' + query : '' ) );
		}

		function setParam( params, name, values ) {
			if ( values.length ) {
				params.set( name, values.join( ',' ) );
			} else {
				params.delete( name );
			}
		}

		function toggle( type, value, chip ) {
			var list = selected[ type ];
			var index = list.indexOf( value );

			if ( index === -1 ) {
				list.push( value );
			} else {
				list.splice( index, 1 );
			}

			var isOn = list.indexOf( value ) !== -1;

			chip.classList.toggle( 'is-active', isOn );
			chip.setAttribute( 'aria-pressed', isOn ? 'true' : 'false' );

			apply();
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				toggle( chip.getAttribute( 'data-fsp-filter' ), chip.getAttribute( 'data-value' ), chip );
			} );
		} );

		if ( resetEl ) {
			resetEl.addEventListener( 'click', function () {
				selected.section = [];
				selected.type = [];

				chips.forEach( function ( chip ) {
					chip.classList.remove( 'is-active' );
					chip.setAttribute( 'aria-pressed', 'false' );
				} );

				apply();
			} );
		}

		/*
		 * Stato iniziale: i filtri arrivano dalla querystring (link
		 * condiviso o arrivo da una scheda) oppure dai chip che il PHP ha
		 * già segnato come attivi sugli archivi di tassonomia.
		 */
		var params = new URLSearchParams( window.location.search );

		readParam( params, 'sezione', 'section' );
		readParam( params, 'tipologia', 'type' );

		function readParam( source, name, type ) {
			var raw = source.get( name );

			if ( raw ) {
				selected[ type ] = raw.split( ',' ).filter( Boolean );
				return;
			}

			// Nessun parametro: si tiene quanto già attivo nel markup.
			chips.forEach( function ( chip ) {
				if ( chip.getAttribute( 'data-fsp-filter' ) === type && chip.classList.contains( 'is-active' ) ) {
					selected[ type ].push( chip.getAttribute( 'data-value' ) );
				}
			} );
		}

		/*
		 * Il pannello si chiude qui e non nel PHP: se il JavaScript non
		 * gira, l'attributo hidden non verrebbe mai tolto e i filtri
		 * resterebbero irraggiungibili dietro a un pulsante inerte.
		 *
		 * Resta aperto quando si arriva con dei filtri già attivi (link
		 * condiviso, ritorno da una scheda): in quel caso la prima cosa da
		 * capire è quali filtri siano in funzione.
		 */
		if ( toggleEl && panelEl ) {
			setPanel( selected.section.length + selected.type.length > 0 );

			toggleEl.addEventListener( 'click', function () {
				setPanel( 'true' !== toggleEl.getAttribute( 'aria-expanded' ) );
			} );
		}

		function setPanel( open ) {
			panelEl.hidden = ! open;
			toggleEl.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			toggleEl.classList.toggle( 'is-open', open );
		}

		// Allinea i chip allo stato appena letto.
		chips.forEach( function ( chip ) {
			var type = chip.getAttribute( 'data-fsp-filter' );
			var isOn = selected[ type ].indexOf( chip.getAttribute( 'data-value' ) ) !== -1;

			chip.classList.toggle( 'is-active', isOn );
			chip.setAttribute( 'aria-pressed', isOn ? 'true' : 'false' );
		} );

		apply();
	}

	/* ------------------------------------------------------------------
	 * Galleria della scheda: le miniature scambiano l'immagine grande
	 * ------------------------------------------------------------------ */

	/**
	 * Cliccare una miniatura porta quella foto nel riquadro grande, non
	 * a tutto schermo. Il pieno schermo resta un secondo passaggio, dal
	 * click sulla grande: così si possono confrontare più scatti di
	 * seguito senza aprire e chiudere una finestra ogni volta.
	 */
	function initPieceGallery() {
		var main = document.querySelector( '[data-fsp-main-image]' );
		var zoom = document.querySelector( '[data-fsp-zoom]' );
		var thumbs = Array.prototype.slice.call( document.querySelectorAll( '[data-fsp-thumb]' ) );

		if ( ! main || ! zoom || ! thumbs.length ) {
			return;
		}

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				var large = thumb.getAttribute( 'data-large' );
				var full = thumb.getAttribute( 'data-full' );

				if ( ! large || main.getAttribute( 'src' ) === large ) {
					return;
				}

				/*
				 * L'immagine si scambia solo a caricamento avvenuto:
				 * assegnando subito il nuovo src, sui file grandi si
				 * vedrebbe il riquadro vuoto per un istante prima che la
				 * foto compaia.
				 */
				var loader = new Image();

				loader.onload = function () {
					main.classList.add( 'is-swapping' );

					window.setTimeout( function () {
						main.src = large;
						// Il pieno schermo deve aprire la foto che si sta
						// guardando adesso, non quella di partenza.
						zoom.setAttribute( 'data-full', full || large );
						main.classList.remove( 'is-swapping' );
					}, 120 );
				};

				loader.src = large;

				thumbs.forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === thumb );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------------------
	 * Ingrandimento foto
	 * ------------------------------------------------------------------ */

	function initLightbox() {
		var overlay = document.querySelector( '[data-fsp-lightbox-overlay]' );

		if ( ! overlay ) {
			return;
		}

		var image = overlay.querySelector( '[data-fsp-lightbox-image]' );
		var closeBtn = overlay.querySelector( '[data-fsp-lightbox-close]' );
		var lastFocus = null;

		function open( url, trigger ) {
			if ( ! image || ! url ) {
				return;
			}

			lastFocus = trigger || null;
			image.src = url;
			overlay.hidden = false;
			document.body.classList.add( 'fsp-no-scroll' );

			if ( closeBtn ) {
				closeBtn.focus();
			}
		}

		function close() {
			overlay.hidden = true;
			document.body.classList.remove( 'fsp-no-scroll' );

			// L'immagine si svuota per non tenere in memoria uno scatto a
			// piena risoluzione dopo la chiusura.
			if ( image ) {
				image.src = '';
			}

			// Il focus torna al pulsante da cui si era aperto: senza,
			// chi naviga da tastiera ripartirebbe dall'inizio della pagina.
			if ( lastFocus ) {
				lastFocus.focus();
				lastFocus = null;
			}
		}

		document.addEventListener( 'click', function ( event ) {
			var trigger = event.target.closest ? event.target.closest( '[data-fsp-zoom]' ) : null;

			if ( trigger ) {
				event.preventDefault();
				open( trigger.getAttribute( 'data-full' ), trigger );
			}
		} );

		overlay.addEventListener( 'click', function ( event ) {
			// Click sullo sfondo o sulla X: la foto stessa non chiude, così
			// non si esce per sbaglio mentre la si guarda.
			if ( event.target === overlay || ( closeBtn && closeBtn.contains( event.target ) ) ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! overlay.hidden ) {
				close();
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Fumo animato di sfondo
	 * ------------------------------------------------------------------ */

	/** Larghezza sotto la quale si considera di essere su un telefono. */
	var MOBILE_WIDTH = 700;

	/* ------------------------------------------------------------------
	 * Texture del fumo, disegnata via codice
	 * ------------------------------------------------------------------ */

	/**
	 * Rumore deterministico a partire da due coordinate.
	 *
	 * Math.imul e non l'operatore * perché in JavaScript i numeri sono a
	 * virgola mobile: moltiplicando interi grandi si perderebbero le
	 * cifre basse, cioè proprio quelle da cui dipende la casualità.
	 *
	 * @param {number} x    Coordinata.
	 * @param {number} y    Coordinata.
	 * @param {number} seed Variante.
	 * @return {number} Fra 0 e 1.
	 */
	function hash2( x, y, seed ) {
		var h = Math.imul( x, 374761393 ) + Math.imul( y, 668265263 ) + Math.imul( seed, 1442695041 );

		h = Math.imul( h ^ ( h >>> 13 ), 1274126177 );

		return ( ( h ^ ( h >>> 16 ) ) >>> 0 ) / 4294967295;
	}

	/**
	 * Rumore continuo: si prendono i valori casuali sui quattro angoli
	 * della cella e si interpola fra loro. L'interpolazione è addolcita
	 * con una curva a S, perché quella lineare lascia visibili gli spigoli
	 * della griglia e il fumo sembrerebbe fatto di rombi.
	 *
	 * @param {number} x    Coordinata.
	 * @param {number} y    Coordinata.
	 * @param {number} seed Variante.
	 * @return {number} Fra 0 e 1.
	 */
	function valueNoise( x, y, seed ) {
		var xi = Math.floor( x );
		var yi = Math.floor( y );
		var xf = x - xi;
		var yf = y - yi;

		var sx = xf * xf * ( 3 - 2 * xf );
		var sy = yf * yf * ( 3 - 2 * yf );

		var a = hash2( xi, yi, seed );
		var b = hash2( xi + 1, yi, seed );
		var c = hash2( xi, yi + 1, seed );
		var d = hash2( xi + 1, yi + 1, seed );

		var top = a + ( b - a ) * sx;
		var bottom = c + ( d - c ) * sx;

		return top + ( bottom - top ) * sy;
	}

	/**
	 * Disegna una voluta di fumo su un canvas fuori schermo.
	 *
	 * Il pen di riferimento caricava un PNG da un server esterno. Qui la
	 * si genera: niente file da scaricare, niente immagine di terzi da
	 * ridistribuire nel plugin, e la densità si regola con dei numeri
	 * invece che riaprendo Photoshop.
	 *
	 * Quattro passate di rumore a frequenza doppia e peso dimezzato (il
	 * cosiddetto rumore frattale) danno le sfilacciature: una passata
	 * sola sarebbe una nuvola tonda e regolare, che non somiglia a niente.
	 * Alla fine si moltiplica per una sfumatura circolare, così i bordi
	 * svaniscono e le volute si fondono fra loro invece di mostrare il
	 * quadrato che le contiene.
	 *
	 * @param {Array}  rgb  Colore del fumo, tre componenti 0-255.
	 * @param {number} seed Variante, per non avere tutte le volute uguali.
	 * @return {HTMLCanvasElement}
	 */
	function createSmokeSprite( rgb, seed ) {
		var size = 192;
		var canvas = document.createElement( 'canvas' );
		var ctx = canvas.getContext( '2d' );

		canvas.width = size;
		canvas.height = size;

		var image = ctx.createImageData( size, size );
		var data = image.data;
		var half = size / 2;

		for ( var y = 0; y < size; y++ ) {
			for ( var x = 0; x < size; x++ ) {
				var value = 0;
				var amplitude = .5;
				var frequency = 3 / size;

				for ( var octave = 0; octave < 4; octave++ ) {
					value += valueNoise( x * frequency, y * frequency, seed + octave ) * amplitude;
					frequency *= 2;
					amplitude *= .5;
				}

				// Sfumatura circolare: 1 al centro, 0 sul bordo.
				var dx = ( x - half ) / half;
				var dy = ( y - half ) / half;
				var distance = Math.sqrt( dx * dx + dy * dy );
				var falloff = Math.max( 0, 1 - distance );

				falloff *= falloff;

				// Il contrasto sul rumore stacca le volute dal grigio piatto.
				var alpha = Math.max( 0, ( value - .38 ) * 2.6 ) * falloff;
				var index = ( y * size + x ) * 4;

				data[ index ] = rgb[ 0 ];
				data[ index + 1 ] = rgb[ 1 ];
				data[ index + 2 ] = rgb[ 2 ];
				data[ index + 3 ] = Math.min( 255, alpha * 255 );
			}
		}

		ctx.putImageData( image, 0, 0 );

		return canvas;
	}

	/**
	 * Converte un colore esadecimale nelle tre componenti.
	 *
	 * @param {string} hex Per esempio "#8fb6c8".
	 * @return {Array}
	 */
	function hexToRgb( hex ) {
		var match = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec( String( hex || '' ) );

		if ( ! match ) {
			return [ 143, 182, 200 ];
		}

		return [ parseInt( match[ 1 ], 16 ), parseInt( match[ 2 ], 16 ), parseInt( match[ 3 ], 16 ) ];
	}

	/**
	 * Colore del fumo scelto nelle impostazioni.
	 *
	 * @return {Array}
	 */
	function smokeColor() {
		var root = document.querySelector( '[data-smoke-color]' );

		return hexToRgb( root ? root.getAttribute( 'data-smoke-color' ) : '' );
	}

	/**
	 * Volute di fumo lente sopra allo sfondo, disegnate su un canvas.
	 *
	 * Tre accorgimenti tengono basso il costo, perché questo è codice che
	 * gira per tutto il tempo in cui la pagina resta aperta:
	 *
	 * 1. lo sbuffo è disegnato UNA volta sola su un canvas fuori schermo
	 *    e poi ricopiato: ricalcolare una ventina di gradienti radiali ad
	 *    ogni fotogramma è l'errore che fa scaldare i telefoni;
	 * 2. si va a 30 fotogrammi al secondo invece di 60 — il fumo si muove
	 *    piano e la differenza non si vede, il lavoro si dimezza;
	 * 3. quando la scheda passa in secondo piano l'animazione si ferma
	 *    del tutto, invece di continuare a disegnare per nessuno.
	 */
	function initSmoke() {
		var host = document.querySelector( '[data-fsp-smoke]' );

		if ( ! host ) {
			return;
		}

		var root = host.closest( '[data-bg-effect]' );

		if ( ! root || 'smoke' !== root.getAttribute( 'data-bg-effect' ) ) {
			return;
		}

		// Su telefono l'effetto parte solo se è stato chiesto esplicitamente.
		if ( window.innerWidth <= MOBILE_WIDTH && '1' !== root.getAttribute( 'data-bg-effect-mobile' ) ) {
			return;
		}

		// Chi ha chiesto meno animazioni al sistema operativo non vede il
		// fumo affatto: resta lo sfondo fermo, che è già un bel risultato.
		if ( prefersReducedMotion() ) {
			return;
		}

		var canvas = document.createElement( 'canvas' );
		var ctx = canvas.getContext( '2d' );

		if ( ! ctx ) {
			return;
		}

		host.appendChild( canvas );

		var puff = createSmokeSprite( smokeColor(), 11 );
		var puffs = [];
		var width = 0;
		var height = 0;
		var ratio = 1;
		var running = true;
		var lastFrame = 0;

		function resize() {
			width = host.offsetWidth || window.innerWidth;
			height = host.offsetHeight || window.innerHeight;

			ratio = drawingRatio();

			canvas.width = Math.round( width * ratio );
			canvas.height = Math.round( height * ratio );
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';

			ctx.setTransform( ratio, 0, 0, ratio, 0, 0 );
		}

		function seed() {
			puffs = [];

			// Meno sbuffi su schermo stretto: l'area è minore e restano
			// comunque fitti.
			var count = width < 900 ? 9 : 16;

			for ( var i = 0; i < count; i++ ) {
				puffs.push( makePuff( true ) );
			}
		}

		/**
		 * @param {boolean} anywhere True per distribuirlo su tutta l'altezza
		 *                           (al primo caricamento), false per farlo
		 *                           entrare dal basso.
		 */
		function makePuff( anywhere ) {
			var size = 260 + Math.random() * 420;

			return {
				x: Math.random() * width,
				y: anywhere ? Math.random() * height : height + size / 2,
				size: size,
				// Velocità di salita in pixel al secondo: molto lenta, il
				// fumo deve sembrare fermo se lo guardi un istante.
				speed: 6 + Math.random() * 10,
				drift: ( Math.random() - .5 ) * 8,
				alpha: .35 + Math.random() * .45,
				phase: Math.random() * Math.PI * 2
			};
		}

		function frame( now ) {
			if ( ! running ) {
				return;
			}

			window.requestAnimationFrame( frame );

			// Tetto a 30 fotogrammi al secondo.
			if ( now - lastFrame < 33 ) {
				return;
			}

			var elapsed = lastFrame ? Math.min( ( now - lastFrame ) / 1000, .2 ) : 0;
			lastFrame = now;

			ctx.clearRect( 0, 0, width, height );

			for ( var i = 0; i < puffs.length; i++ ) {
				var p = puffs[ i ];

				p.y -= p.speed * elapsed;
				p.phase += elapsed * .35;
				p.x += ( p.drift + Math.sin( p.phase ) * 6 ) * elapsed;

				// Uscito dall'alto: si ricicla dal basso invece di crearne
				// uno nuovo, così il numero di oggetti resta costante.
				if ( p.y + p.size / 2 < 0 ) {
					puffs[ i ] = makePuff( false );
					continue;
				}

				ctx.globalAlpha = p.alpha;
				ctx.drawImage( puff, p.x - p.size / 2, p.y - p.size / 2, p.size, p.size );
			}

			ctx.globalAlpha = 1;
		}

		resize();
		seed();
		window.requestAnimationFrame( frame );

		onWidthChange( function () {
			var oldWidth = width;
			var oldHeight = height;

			resize();

			// Le volute si riscalano invece di essere ricreate: ricrearle
			// faceva sparire e ricomparire il fumo ad ogni cambio di
			// dimensione.
			if ( oldWidth && oldHeight ) {
				var scaleX = width / oldWidth;
				var scaleY = height / oldHeight;

				puffs.forEach( function ( p ) {
					p.x *= scaleX;
					p.y *= scaleY;
				} );
			} else {
				seed();
			}
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				running = false;
				return;
			}

			running = true;
			// Azzerato, altrimenti al rientro il primo fotogramma
			// recupererebbe in un colpo tutto il tempo passato in pausa e
			// il fumo farebbe un salto.
			lastFrame = 0;
			window.requestAnimationFrame( frame );
		} );
	}

	/* ------------------------------------------------------------------
	 * Logo immerso nel fumo
	 * ------------------------------------------------------------------ */

	/**
	 * A che definizione disegnare il fumo.
	 *
	 * Il fumo è tutto sfocato: la definizione in più non si vedrebbe,
	 * mentre l'area da disegnare cresce con il quadrato del rapporto. Su
	 * telefono si scende ancora, perché lì ogni pixel risparmiato è
	 * batteria e fluidità — un valore di 1,25 invece di 2 significa
	 * disegnare il 61% dei pixel in meno.
	 *
	 * @return {number}
	 */
	function drawingRatio() {
		var dpr = window.devicePixelRatio || 1;
		var cap = window.innerWidth <= MOBILE_WIDTH ? 1.25 : 1.5;

		return Math.min( dpr, cap );
	}

	/**
	 * Chiama la funzione data solo quando cambia la LARGHEZZA della
	 * finestra.
	 *
	 * Su un telefono, scorrendo, la barra degli indirizzi si nasconde e
	 * si rimostra: cambia l'altezza della finestra e il browser emette
	 * una raffica di eventi di ridimensionamento — ne ho contati nove per
	 * un solo movimento della barra. Reagendo a quegli eventi il fumo
	 * veniva rigenerato da capo, con le volute in posizioni nuove: è il
	 * "reset" che si vedeva scorrendo.
	 *
	 * La larghezza invece, scorrendo, non cambia mai. Filtrando su quella
	 * restano solo i ridimensionamenti veri: rotazione dello schermo o
	 * finestra ridimensionata sul computer.
	 *
	 * @param {Function} callback Cosa fare a larghezza cambiata.
	 */
	function onWidthChange( callback ) {
		var lastWidth = window.innerWidth;
		var timer = null;

		window.addEventListener( 'resize', function () {
			if ( window.innerWidth === lastWidth ) {
				return;
			}

			lastWidth = window.innerWidth;

			// Durante una rotazione o un trascinamento del bordo gli eventi
			// arrivano comunque a raffica: si aspetta che si fermino.
			window.clearTimeout( timer );
			timer = window.setTimeout( callback, 200 );
		} );
	}

	/**
	 * Manopole dell'effetto, dalle impostazioni.
	 *
	 * Arrivano tutte come valori da 0 a 100 e vengono qui tradotte nelle
	 * unità che servono davvero. La traduzione sta in un punto solo così
	 * i due livelli di fumo restano coerenti fra loro.
	 *
	 * @return {Object}
	 */
	function smokeParams() {
		var root = document.querySelector( '[data-smoke-params]' );
		var raw = parseJSON( root ? root.getAttribute( 'data-smoke-params' ) : '' ) || {};

		var intensity = clamp01( raw.intensity, 55 );
		var opacity = clamp01( raw.opacity, 55 );
		var speed = clamp01( raw.speed, 40 );
		var size = clamp01( raw.size, 55 );

		return {
			// Superficie che compete a una voluta: più alta l'intensità,
			// meno superficie ciascuna, quindi più volute.
			areaPerPuff: 40000 - intensity * 340,
			alphaBase: .08 + ( opacity / 100 ) * .5,
			alphaSpread: .1 + ( opacity / 100 ) * .35,
			speed: speed / 50,
			sizeFactor: .45 + ( size / 100 ) * 1.15
		};
	}

	/**
	 * @param {*}      value    Valore grezzo.
	 * @param {number} fallback Valore se manca o non è un numero.
	 * @return {number} Fra 0 e 100.
	 */
	function clamp01( value, fallback ) {
		var n = parseInt( value, 10 );

		if ( isNaN( n ) ) {
			return fallback;
		}

		return Math.max( 0, Math.min( 100, n ) );
	}

	/**
	 * Costruisce un livello di fumo dentro a un contenitore.
	 *
	 * Il logo NON viene disegnato qui dentro: è rimasto un'immagine vera
	 * in pagina, e deve poter scorrere per conto suo. L'effetto "dentro
	 * al fumo" nasce dalla sovrapposizione di due livelli, uno sotto e
	 * uno sopra al logo, invece che dall'ordine di disegno dentro un
	 * canvas solo.
	 *
	 * @param {Element} host Contenitore del livello.
	 */
	function buildSmokeLayer( host ) {
		var canvas = document.createElement( 'canvas' );
		var ctx = canvas.getContext( '2d' );

		if ( ! ctx ) {
			return;
		}

		canvas.className = 'fsp-brand-smoke__canvas';
		host.appendChild( canvas );

		var params = smokeParams();
		var rgb = smokeColor();
		var sprites = [ createSmokeSprite( rgb, 3 ), createSmokeSprite( rgb, 17 ), createSmokeSprite( rgb, 41 ) ];
		var puffs = [];
		var width = 0;
		var height = 0;
		var ratio = 1;
		var running = true;
		var lastFrame = 0;

		function resize() {
			width = host.offsetWidth;
			height = host.offsetHeight;

			if ( ! width || ! height ) {
				return false;
			}

			ratio = drawingRatio();

			canvas.width = Math.round( width * ratio );
			canvas.height = Math.round( height * ratio );

			ctx.setTransform( ratio, 0, 0, ratio, 0, 0 );

			return true;
		}

		/**
		 * Quante volute servono per la superficie attuale.
		 *
		 * Il numero segue l'area e non è fisso: a numero fisso il fumo
		 * risulta fitto su uno schermo piccolo e rado fino a sparire su un
		 * monitor largo. I due limiti evitano gli estremi.
		 *
		 * @return {number}
		 */
		function puffCount() {
			var count = Math.round( ( width * height ) / Math.max( 4000, params.areaPerPuff ) );

			return Math.max( 4, Math.min( 60, count ) );
		}

		function seed() {
			puffs = [];

			var count = puffCount();

			for ( var i = 0; i < count; i++ ) {
				puffs.push( makePuff() );
			}
		}

		/**
		 * Adatta le volute esistenti alle nuove dimensioni invece di
		 * ricrearle.
		 *
		 * Ricreandole, a ogni ridimensionamento vero il fumo sparirebbe e
		 * ricomparirebbe altrove — lo stesso salto che si vedeva scorrendo
		 * su un telefono. Riscalando le posizioni, il fumo si adatta
		 * continuando il movimento che stava facendo.
		 *
		 * @param {number} oldWidth  Larghezza precedente.
		 * @param {number} oldHeight Altezza precedente.
		 */
		function reflow( oldWidth, oldHeight ) {
			if ( ! oldWidth || ! oldHeight ) {
				seed();
				return;
			}

			var scaleX = width / oldWidth;
			var scaleY = height / oldHeight;

			puffs.forEach( function ( p ) {
				p.x *= scaleX;
				p.y *= scaleY;
			} );

			// Il numero dipende dall'area: si aggiunge o si toglie solo la
			// differenza, lasciando al loro posto le volute già in scena.
			var target = puffCount();

			while ( puffs.length < target ) {
				puffs.push( makePuff() );
			}

			if ( puffs.length > target ) {
				puffs.length = target;
			}
		}

		function makePuff() {
			var base = Math.min( height, width * .5 );

			return {
				x: Math.random() * width,
				y: Math.random() * height,
				size: base * params.sizeFactor * ( .7 + Math.random() * .8 ),
				angle: Math.random() * Math.PI * 2,
				spin: ( Math.random() - .5 ) * .2 * params.speed,
				driftX: ( Math.random() - .5 ) * 16 * params.speed,
				driftY: -( 2 + Math.random() * 7 ) * params.speed,
				alpha: params.alphaBase + Math.random() * params.alphaSpread,
				sprite: sprites[ Math.floor( Math.random() * sprites.length ) ]
			};
		}

		function frame( now ) {
			if ( ! running ) {
				return;
			}

			window.requestAnimationFrame( frame );

			// Tetto a 30 fotogrammi al secondo: il fumo si muove piano e la
			// differenza non si vede, il lavoro si dimezza.
			if ( now - lastFrame < 33 ) {
				return;
			}

			var elapsed = lastFrame ? Math.min( ( now - lastFrame ) / 1000, .2 ) : 0;
			lastFrame = now;

			ctx.setTransform( ratio, 0, 0, ratio, 0, 0 );
			ctx.clearRect( 0, 0, width, height );
			ctx.globalCompositeOperation = 'lighter';

			for ( var i = 0; i < puffs.length; i++ ) {
				var p = puffs[ i ];

				p.angle += p.spin * elapsed;
				p.x += p.driftX * elapsed;
				p.y += p.driftY * elapsed;

				// Uscita dai bordi: si rientra dal lato opposto, così la
				// scena non si svuota mai.
				if ( p.y + p.size / 2 < 0 ) {
					p.y = height + p.size / 2;
				}

				if ( p.x - p.size / 2 > width ) {
					p.x = -p.size / 2;
				} else if ( p.x + p.size / 2 < 0 ) {
					p.x = width + p.size / 2;
				}

				ctx.save();
				ctx.globalAlpha = p.alpha;
				ctx.translate( p.x, p.y );
				ctx.rotate( p.angle );
				ctx.drawImage( p.sprite, -p.size / 2, -p.size / 2, p.size, p.size );
				ctx.restore();
			}

			ctx.globalAlpha = 1;
			ctx.globalCompositeOperation = 'source-over';
		}

		if ( ! resize() ) {
			return;
		}

		seed();
		host.classList.add( 'is-painted' );
		window.requestAnimationFrame( frame );

		onWidthChange( function () {
			var oldWidth = width;
			var oldHeight = height;

			if ( resize() ) {
				reflow( oldWidth, oldHeight );
			}
		} );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				running = false;
				return;
			}

			running = true;
			// Azzerato, altrimenti al rientro il primo fotogramma
			// recupererebbe in un colpo tutto il tempo passato in pausa.
			lastFrame = 0;
			window.requestAnimationFrame( frame );
		} );
	}

	/**
	 * Accende i due livelli di fumo attorno al marchio.
	 */
	function initBrandSmoke() {
		var layers = document.querySelectorAll( '[data-fsp-brand-smoke]' );

		if ( ! layers.length || prefersReducedMotion() ) {
			return;
		}

		var root = document.querySelector( '[data-bg-effect-mobile]' );
		var allowMobile = root && '1' === root.getAttribute( 'data-bg-effect-mobile' );

		if ( window.innerWidth <= MOBILE_WIDTH && ! allowMobile ) {
			return;
		}

		Array.prototype.forEach.call( layers, buildSmokeLayer );
	}

	/* ------------------------------------------------------------------
	 * Copia del codice pezzo
	 * ------------------------------------------------------------------ */

	/**
	 * Instagram non accetta un messaggio precompilato in un link: il
	 * pulsante copia allora il riferimento del pezzo negli appunti e
	 * lascia aprire il profilo, così al visitatore basta incollare.
	 *
	 * La copia non deve mai bloccare l'apertura del link: se gli appunti
	 * non sono disponibili (contesto non sicuro, permesso negato,
	 * browser vecchio) si va su Instagram lo stesso.
	 */
	function initCopy() {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( '[data-fsp-copy]' ) : null;

			if ( ! link ) {
				return;
			}

			var text = link.getAttribute( 'data-fsp-copy' );

			if ( ! text || ! navigator.clipboard || ! navigator.clipboard.writeText ) {
				return;
			}

			navigator.clipboard.writeText( text ).then( function () {
				flash( link, l10n.copied || 'Codice copiato' );
			} ).catch( function () {
				flash( link, l10n.copyFailed || 'Copia non riuscita' );
			} );
		} );
	}

	/**
	 * Mostra per un attimo un messaggio sul pulsante, poi rimette
	 * l'etichetta originale.
	 *
	 * @param {Element} el      Pulsante.
	 * @param {string}  message Testo temporaneo.
	 */
	function flash( el, message ) {
		if ( el.dataset.fspFlashing ) {
			return;
		}

		var original = el.textContent;

		el.dataset.fspFlashing = '1';
		el.textContent = message;

		window.setTimeout( function () {
			el.textContent = original;
			delete el.dataset.fspFlashing;
		}, 1600 );
	}

	/**
	 * @param {string} raw JSON da un attributo data.
	 * @return {Object|null}
	 */
	function parseJSON( raw ) {
		if ( ! raw ) {
			return null;
		}

		try {
			return JSON.parse( raw );
		} catch ( error ) {
			return null;
		}
	}

	function init() {
		initFilters();
		initPieceGallery();
		initLightbox();
		initCopy();
		initSmoke();
		initBrandSmoke();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
