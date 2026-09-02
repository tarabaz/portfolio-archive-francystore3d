# FrancyStore Portfolio

Plugin WordPress per il portfolio di **FrancyStore3D**: action figure, lampade,
diorami, stand e pezzi personalizzati stampati in 3D, rifiniti e dipinti a mano.

È una vetrina, non un negozio: nessun prezzo, nessun carrello. Chi è interessato
a un pezzo scrive su Instagram.

## Cosa fa

- **Archivio filtrabile in tempo reale** — sezioni e tipologie a selezione
  multipla, con riordino animato delle schede e nessun ricaricamento di pagina
- **Pagina indipendente dal tema** — il portfolio ha il suo layout completo,
  cambiare tema non lo tocca
- **Sfondi a più livelli** — ogni pezzo può avere il suo sfondo, altrimenti
  eredita quello della sezione e infine quello generale
- **Schede tecniche flessibili** — campi base fissi più righe libere
  chiave/valore, per gestire lampade e figure senza campi vuoti
- **Compilazione per campi** — nessun editor da impaginare: titolo, descrizione,
  immagini e dati, e basta
- **Contatto Instagram** — con il codice del pezzo copiato negli appunti, così
  chi scrive ha già il riferimento pronto

## Installazione

1. Scarica il repository come ZIP da GitHub (pulsante **Code → Download ZIP**)
2. WordPress → **Plugin → Aggiungi nuovo → Carica plugin**
3. Seleziona lo ZIP e attiva
4. Vai su **Portfolio → Impostazioni** e compila almeno il profilo Instagram
5. Crea le sezioni da **Portfolio → Sezioni** (Lampade, Action figure, Diorami…)
   e le tipologie da **Portfolio → Tipologie** (Anime, Fantasy, Videogiochi…)
6. Aggiungi il primo pezzo da **Portfolio → Aggiungi pezzo**

Il portfolio è online su `iltuosito.it/portfolio`. L'indirizzo si cambia dalle
impostazioni.

> Se dopo l'attivazione le schede dei pezzi danno errore 404, vai su
> **Impostazioni → Permalink** e premi Salva: rigenera le regole di riscrittura.

## Compilare un pezzo

| Campo | Dove |
| --- | --- |
| Titolo | campo in cima alla schermata |
| Descrizione | box *Scheda del pezzo*, sezione *Descrizione* |
| Immagine principale (quella della griglia) | box *Scheda del pezzo*, sezione *Immagini* |
| Altre immagini | box *Scheda del pezzo*, accanto alla principale |
| Codice, materiale, altezza, tempo, anno | box *Scheda del pezzo*, sezione *Dati base* |
| Alimentazione, illuminazione, scala… | box *Scheda del pezzo*, tabella *Altri dati* |
| Sfondo della scheda | box *Scheda del pezzo*, in fondo |
| Sezione e tipologia | colonna destra, a spunte |

I campi lasciati vuoti non compaiono nella scheda pubblica.

## Requisiti

- WordPress 6.0 o superiore
- PHP 8.0 o superiore

## Struttura del repository

```
francystore-portfolio.php   File principale del plugin
includes/                   Classi PHP, una per area funzionale
templates/                  Template di frontend (sovrascrivibili dal tema)
assets/                     CSS e JavaScript
```

I template si possono personalizzare dal tema copiandoli in
`wp-content/themes/<tema>/francystore-portfolio/`.
