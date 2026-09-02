# FrancyStore Portfolio — note per le sessioni future

Plugin WordPress che pubblica il portfolio dei pezzi realizzati da FrancyStore3D
(action figure, lampade, diorami, stand). È una vetrina: niente WooCommerce,
niente carrello, niente prezzi. Il contatto avviene su Instagram.

## REGOLA GIT — vale sempre, senza chiedere

Valerio non tocca git a mano. Per **ogni** modifica, il flusso completo è a
carico di Claude:

1. commit sul branch `claude/vegapunk-archive-plugin-v35em4`
2. `git push -u origin claude/vegapunk-archive-plugin-v35em4`
3. `git checkout main && git merge claude/vegapunk-archive-plugin-v35em4`
4. `git push -u origin main`
5. tornare sul branch di lavoro

I file aggiornati devono **sempre** essere presenti su `origin/main`: è da lì che
Valerio scarica lo zip da caricare su WordPress. Niente pull request, niente
merge lasciati a lui, nessun passaggio git da fare a mano.

## Convenzioni di codice

- Prefisso di tutto: `fsp_` / `FSP_` (costanti, classi, meta, opzioni, CSS, JS).
  È il prefisso definitivo, sta nel database: non va cambiato.
- Text domain: `francystore-portfolio`. Interfaccia in italiano.
- Ogni classe in `includes/` copre una sola area e si auto-registra sui propri
  hook da `init()`, richiamato da `fsp_init_plugin()`.
- PHP 8.3 sul server di produzione, ma il codice resta compatibile con 8.0
  (dichiarato nell'header del plugin).
- **Bump di `FSP_VERSION` ad ogni modifica di CSS o JS**: è il cache-busting
  degli asset. Senza, il browser continua a servire i file vecchi e sembra che
  la modifica non sia stata caricata.
- Commenti in italiano che spiegano il *perché* di una scelta, non il *cosa* fa
  la riga. Stesso stile del plugin Devil Fruit Archive.

## Architettura

| File | Ruolo |
| --- | --- |
| `francystore-portfolio.php` | header, costanti, require, attivazione |
| `includes/class-fsp-cpt.php` | CPT `fsp_pezzo`, query dell'archivio |
| `includes/class-fsp-taxonomies.php` | `fsp_sezione` e `fsp_tag` (entrambe gerarchiche, la seconda mostrata come "Tipologia"), sfondo per sezione |
| `includes/class-fsp-meta.php` | campi base, attributi liberi, galleria, sfondo e priorità |
| `includes/class-fsp-metabox.php` | compilazione del pezzo in wp-admin |
| `includes/class-fsp-admin-columns.php` | colonne miniatura e codice in lista |
| `includes/class-fsp-settings.php` | pagina impostazioni |
| `includes/class-fsp-template-loader.php` | template standalone + assets |
| `templates/` | archivio, scheda singola, parti |
| `assets/` | CSS e JS di frontend e backend |

## Scelte da non rifare da capo

**Pagina indipendente dal tema.** I template sono documenti HTML completi:
niente `get_header()`/`get_footer()`. Però `wp_head()` e `wp_footer()` vanno
chiamati lo stesso — è lì che si agganciano pixel Meta, analytics e cookie
banner, e Valerio fa advertising su Meta: saltarli significa perdere il
tracciamento proprio sulle pagine che promuove. Degli stili del tema ci si
libera con il dequeue mirato in `FSP_Template_Loader::dequeue_theme_styles()`.

**Slug dell'archivio: `portfolio`, non `archivio`.** Sullo stesso sito gira il
plugin Devil Fruit Archive, che occupa già `/archivio`. Due CPT sulla stessa
rewrite si rubano le richieste a vicenda.

**Categorie e tag sono tassonomie vere**, non una lista di stringhe nelle
impostazioni: si gestiscono dalle schermate standard di WordPress, che danno
già slug, conteggi e assegnazione rapida.

**Campi ibridi.** Cinque campi base fissi (codice, materiale, altezza, tempo,
anno) più una tabella di attributi liberi chiave/valore. Serve perché una
lampada ha "alimentazione" e "tipo illuminazione" e una figure no: con campi
tutti fissi metà archivio resterebbe vuoto. Le etichette suggerite si
configurano dalle impostazioni e compaiono in autocomplete.

**Filtri client-side con FLIP.** Tutti i pezzi sono in pagina (`posts_per_page
= -1`) e i filtri lavorano in JavaScript sul DOM. Niente paginazione: con le
pagine, filtrare mostrerebbe solo i risultati della pagina corrente. Il
riordino animato usa FLIP scritto a mano, senza Isotope/Shuffle. Regge
comodamente il centinaio di pezzi previsto e la crescita di un paio al mese.

**Logica dei filtri: OR dentro il gruppo, AND tra i gruppi.** "Lampade" +
"Diorami" + tipologia "Anime" mostra le lampade e i diorami a tema anime.

**La tassonomia interna si chiama ancora `fsp_tag`, ma ovunque si legge
"Tipologia".** Rinominare lo slug staccherebbe i termini già assegnati ai pezzi.
È gerarchica non per fare sottocategorie ma per il tipo di campo: WordPress
mostra le gerarchiche a spunte e le piatte come testo libero, e a spunte non ci
si ritrova "anime" / "Anime" / "anime " come tre termini distinti.

**Niente editor a blocchi.** Il CPT non dichiara `editor` e ha
`show_in_rest => false`, così WordPress serve la schermata classica e i meta box
stanno sotto al titolo invece che schiacciati di lato. La descrizione si scrive
in un campo del meta box che però salva in `post_content` (via il filtro
`wp_insert_post_data`, non con un secondo update dentro `save_post`): i testi
scritti quando l'editor c'era ancora restano leggibili e modificabili.

**Immagine principale = featured image.** Il meta box ha un campo dedicato, ma
sotto usa `set_post_thumbnail()`: resta l'immagine che WordPress conosce e che
finisce nelle anteprime di condivisione. Se manca, la scheda ripiega sulla prima
della galleria, e quell'immagine viene tolta dalle miniature per non mostrarla
due volte.

**Priorità dello sfondo della scheda: pezzo → sezione → generale.** La logica
sta in `FSP_Meta::get_background_id()`, un punto solo per tutti i template.

**Le miniature scambiano l'immagine grande, non aprono il pieno schermo.**
Il pieno schermo è il secondo click, sulla grande: così si confrontano più
scatti di seguito senza chiudere e riaprire una finestra ogni volta. La
principale sta anche fra le miniature, altrimenti dopo il primo click non ci si
tornerebbe più.

**Il pannello dei filtri parte chiuso, ma è il JS a chiuderlo, non il PHP.**
Se lo chiudesse il PHP con `hidden`, senza JavaScript resterebbe irraggiungibile
dietro a un pulsante inerte. Resta aperto quando si arriva con filtri già attivi
da querystring. Il numero sul pulsante è l'unico segnale, a pannello chiuso, che
la griglia non sta mostrando tutto — per questo conteggio pezzi e "azzera" stanno
fuori dal pannello.

**Logo dell'intestazione al posto del titolo, con altezza dalle impostazioni.**
L'altezza passa da una variabile CSS inline (`--fsp-logo-height`) e non da un
`height` diretto, così il CSS può ricalcolarla su schermo stretto. Senza logo si
stampa il titolo scritto: l'intestazione non è mai vuota.

**Sfondo fermo con `position: fixed` su un elemento, non
`background-attachment: fixed`.** La seconda su iOS non funziona da anni e dove
funziona costa di più. Attenzione: `position: fixed` si aggancia al primo
antenato con `transform`/`filter`/`contain` — se un domani se ne aggiunge uno su
`.fsp-archive` o `.fsp-single`, lo sfondo si sgancia e torna a scorrere.

**L'effetto "logo nel fumo" ricrea in canvas 2D il pen three.js che Valerio
voleva** (https://codepen.io/teolitto/pen/KwOVvL — quel codice non gira più: usa
`THREE.CubeGeometry` e `THREE.ImageUtils`, rimossi da three.js). Quello che nel 3D
faceva la coordinata z lo fa qui l'ordine di disegno: volute dietro → marchio →
volute davanti; l'`AdditiveBlending` diventa `globalCompositeOperation =
'lighter'`. Costo: zero KB, contro i ~150 KB compressi di three.js.

**La texture del fumo è generata via codice** (rumore frattale a quattro ottave
per le sfilacciature, moltiplicato per una sfumatura circolare perché i bordi
svaniscano). Quella del pen sta su un server di terzi e non è ridistribuibile.

**Il livello del fumo dell'intestazione sta fuori dal contenuto, non dentro al
blocco del logo.** Dentro sarebbe largo quanto la colonna di testo e, per avere
aria attorno al marchio, servirebbero margini veri — che spingono in basso tutta
la pagina. Da fuori si sovrappone al layout senza farne parte: accendere o
spegnere l'effetto non muove nulla (verificato misurando le posizioni con e senza).
Il JS misura `getBoundingClientRect()` del marchio per sapere dove disegnarlo.

**`position: absolute` ancorato in cima, non `fixed`:** il fumo scorre via con
l'intestazione. Fisso allo schermo resterebbe davanti alle foto dei pezzi per
tutta la navigazione.

**Le due dissolvenze del fumo stanno su due elementi annidati** (verticale sul
contenitore, orizzontale sul canvas) invece che su uno solo con `mask-composite`:
quella proprietà è supportata a macchia di leopardo e dove manca il fumo si
taglierebbe di squadro sui bordi dello schermo.

**Numero di volute proporzionale all'area**, non fisso: la fascia è larga quanto
la finestra, e a numero fisso il fumo risulta fitto su uno schermo piccolo e rado
fino a sparire su un monitor largo.

**Il canvas del marchio ha una maschera ovale sui bordi.** Senza, le volute si
tagliano di squadro sul bordo del rettangolo e si vede la cornice del canvas in
mezzo alla pagina.

**Il marchio originale resta in pagina e viene nascosto con `opacity: 0` solo
quando il canvas ha disegnato** (classe `is-painted` messa dal JS a lavoro
finito). Non `display: none`: il canvas si dimensiona su quel blocco e legge da
lì quanto è grande il logo. Così senza JavaScript, con effetto spento o con
immagine non ancora scaricata, l'intestazione non è mai vuota — e il testo resta
leggibile da lettori di schermo e motori di ricerca, che dal canvas non
ricaverebbero nulla.

**Il fumo animato è un canvas, e costa: tre accorgimenti lo tengono a bada.**
Lo sbuffo è disegnato una volta sola su un canvas fuori schermo e poi ricopiato
(ricalcolare venti gradienti radiali per fotogramma è ciò che fa scaldare i
telefoni); il tetto è 30 fps invece di 60; in scheda non visibile l'animazione si
ferma. Misurati in Chromium: 30 fps, 0 disegni in secondo piano.

**L'effetto su telefono si spegne in CSS, non in JavaScript.** Deve valere anche
a script bloccato, ed è proprio lì che conta di più: un telefono che non esegue
lo script è quasi sempre un telefono lento. Il JS fa il suo controllo in più solo
per non far partire il canvas.

**Gli sfondi sfumano in basso con `mask-image`, non con un rettangolo
sovrapposto.** La maschera agisce sull'immagine, quindi la dissolvenza finisce
esattamente dove finisce la foto, qualunque sia l'altezza del box.

**Gli sfondi hanno un box di altezza fissa e usano `object-fit: cover` con
`object-position: center`.** Lasciando decidere all'immagine, una foto verticale
si allunga per mezza pagina e una panoramica lascia una striscia: il formato del
file finirebbe per decidere l'impaginazione.

**Link Instagram del pezzo validato sul dominio** in
`FSP_Meta::sanitize_instagram_url()`: accetta solo instagram.com / instagr.am,
aggiunge lo schema se manca, e su valore scartato lascia un avviso via transient
(fra salvataggio e schermata c'è un redirect, una variabile non sopravviverebbe).

**Sezioni e tipologie nella scheda sono etichette, non link.** Dalla scheda si
vuole che il visitatore legga e poi scriva, non che torni alla griglia.

**Flush automatico delle rewrite rules al cambio di `FSP_VERSION`.**
Aggiornare i file non fa scattare l'hook di attivazione: senza, ogni modifica a
uno slug lascerebbe 404 finché non si risalvano i permalink a mano.

**Sfondo durante il filtraggio:** una sola sezione selezionata → il suo sfondo;
zero o più di una → sfondo generale. Tenere il primo della lista farebbe
cambiare l'ambientazione in base all'ordine dei click.

**Instagram non accetta messaggi precompilati.** Nessun link apre il DM con del
testo già scritto: è un limite della piattaforma, non un pezzo mancante. Per
questo il pulsante copia il codice pezzo negli appunti e apre il profilo. Il
campo WhatsApp (facoltativo) invece il messaggio precompilato lo supporta.

**Niente `uninstall.php`.** Disinstallando, pezzi e impostazioni restano nel
database: cancellare anni di schede compilate a mano per un click di troppo non
è un rischio che vale la pena correre.

## Cosa manca / possibili sviluppi

- Traduzione `.po`/`.mo` in `languages/` (l'interfaccia è già in italiano nei
  sorgenti, i file servirebbero solo per ritoccare le stringhe senza toccare il
  codice)
- Caricamento progressivo via REST, se l'archivio superasse le ~300 schede
- Ordinamento manuale dei pezzi in griglia (il CPT supporta già
  `page-attributes`, l'ordinamento attuale è per data)
