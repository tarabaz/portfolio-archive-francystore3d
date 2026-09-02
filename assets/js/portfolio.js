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

		var puff = buildPuff();
		var puffs = [];
		var width = 0;
		var height = 0;
		var ratio = 1;
		var running = true;
		var lastFrame = 0;

		function resize() {
			width = host.offsetWidth || window.innerWidth;
			height = host.offsetHeight || window.innerHeight;

			/*
			 * Il rapporto pixel si ferma a 1.5 anche sugli schermi che
			 * dichiarano 3: il fumo è tutto sfocato, la definizione in più
			 * non si vedrebbe, mentre l'area da disegnare crescerebbe con
			 * il quadrato del rapporto.
			 */
			ratio = Math.min( window.devicePixelRatio || 1, 1.5 );

			canvas.width = Math.round( width * ratio );
			canvas.height = Math.round( height * ratio );
			canvas.style.width = width + 'px';
			canvas.style.height = height + 'px';

			ctx.setTransform( ratio, 0, 0, ratio, 0, 0 );
		}

		/**
		 * Disegna una volta sola uno sbuffo sfumato su un canvas separato,
		 * da ricopiare poi ad ogni fotogramma.
		 *
		 * @return {HTMLCanvasElement}
		 */
		function buildPuff() {
			var size = 256;
			var off = document.createElement( 'canvas' );
			var octx = off.getContext( '2d' );

			off.width = size;
			off.height = size;

			var gradient = octx.createRadialGradient( size / 2, size / 2, 0, size / 2, size / 2, size / 2 );

			gradient.addColorStop( 0, 'rgba(216, 219, 222, .16)' );
			gradient.addColorStop( .45, 'rgba(216, 219, 222, .06)' );
			gradient.addColorStop( 1, 'rgba(216, 219, 222, 0)' );

			octx.fillStyle = gradient;
			octx.fillRect( 0, 0, size, size );

			return off;
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

		var resizeTimer = null;

		window.addEventListener( 'resize', function () {
			// Su mobile la barra degli indirizzi che compare e scompare
			// genera una raffica di resize: si aspetta che si fermi.
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( function () {
				resize();
				seed();
			}, 200 );
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
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
