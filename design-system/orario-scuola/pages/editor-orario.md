# Editor griglia orario (orari/classe.blade.php)

> Override di MASTER.md per l'unica pagina con interazione non standard (drag&drop) dell'app.

## Vincoli specifici

- Drag&drop in **JS vanilla puro** (HTML5 Drag and Drop API) — nessuna libreria, per vincolo di `CLAUDE.md`
- Ogni cella trascinabile ha `draggable="true"` solo se l'utente ha il permesso `gestisci-anagrafica` **e** la lezione non è bloccata; gli elementi interattivi dentro la cella (select, bottoni) hanno `draggable="false"` per non rubare il gesto di trascinamento al click
- Dopo ogni tentativo di modifica (riuscito o respinto) la pagina ricarica: l'esito si legge nel pannello avvisi persistente, mai in un `alert()`
- Il pannello avvisi usa rosso (`bg-red-50 border-red-200`) se contiene almeno un errore, altrimenti ambra (`bg-amber-50`) per i soli avvisi — mai verde, per non suggerire che "tutto ok" quando in realtà c'è uno sbilanciamento da controllare

## Colori cella

- Lezione normale: `bg-blue-50 border-blue-200`
- Lezione bloccata: `bg-amber-100 border-amber-300`
- Badge compresenza sostegno: testo verde (`text-green-700`), separato da un bordo superiore sottile

## Tabella/griglia

- `overflow-x-auto` sul contenitore (la griglia a 5-6 colonne + pomeridiani non ci sta su mobile)
