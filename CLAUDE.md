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
| `includes/class-fsp-taxonomies.php` | `fsp_sezione` (gerarchica), `fsp_tag` (piatta), sfondo per sezione |
| `includes/class-fsp-meta.php` | campi base, attributi liberi, galleria |
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
"Diorami" + tag "Anime" mostra le lampade e i diorami a tema anime.

**Sfondo durante il filtraggio:** una sola sezione selezionata → il suo sfondo;
zero o più di una → sfondo generale. Tenere il primo della lista farebbe
cambiare l'ambientazione in base all'ordine dei click.

**I termini nella scheda rimandano alla griglia con querystring**
(`?sezione=slug`), non all'archivio della tassonomia: lì il server manderebbe in
pagina solo i pezzi di quel termine e togliere il filtro non farebbe ricomparire
nulla, perché le altre schede non sarebbero nel documento.

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
