# Pagine anagrafica (index / create / edit)

> Override di MASTER.md per le pagine CRUD (sedi, aule, discipline, quadri orari, docenti, classi, cattedre, vincoli, utenze).

## Struttura index

1. `<h1>` da solo (nessun pulsante nell'intestazione)
2. `<x-guida>` — una spiegazione breve di cosa rappresenta l'entità e come si collega alle altre
3. `<x-barra-tabella>` sopra la tabella: **filtri e ricerca a sinistra** (slot predefinito), **azioni a destra** (slot `azioni`: "Nuovo X", "Importa CSV"), visibili solo con `@can('gestisci-...')`
4. `<x-barra-selezione />`: conteggio righe selezionate + "Elimina selezionati" (disabilitato finché non si seleziona qualcosa; si nasconde da sola se non ci sono righe eliminabili)
5. Tabella in un contenitore `bg-white border border-gray-200 rounded-lg overflow-x-auto` (mai `overflow-hidden`: tronca i dati su schermi stretti invece di scorrere); prima colonna = checkbox `.js-sel` (value = URL di eliminazione) e intestazione `.js-sel-tutti`
6. Se l'elenco può crescere oltre ~50 righe: `->paginate(50)->withQueryString()` + `{{ $items->links() }}`

## Creazione e modifica

- **Modale** (link `data-modale`) per le schede semplici; **pagina intera** per docenti e classi (troppe informazioni). Nella modale: chiusura solo con ×, Annulla o Esc (mai con un click fuori); salvando si chiude e la pagina si ricarica.
- **Un solo form** per pagina/modale, con un solo Salva e un solo Annulla **fissi in basso a destra**: `<x-barra-salvataggio>` nelle pagine, piè di pagina della modale nelle modali. Mai pulsanti di salvataggio per sezione.
- **Larghezza intera, campi su due colonne**: classe `.form-colonne` sulla scheda (automatico nelle modali, dove la scheda perde bordo e padding). Niente `max-w-*` sui form.
- Campi condivisi tra create/edit in una partial `_form.blade.php`.
- **Liste ripetibili** (cattedre, righe del quadro, sostegno): `<x-righe-ripetibili>`, aggiunta/rimozione in JS e un solo salvataggio; totali live con `data-totale` / `data-somma`; valori già scelti disattivati con `data-univoca`.
- **Campi obbligatori**: attributo `required` → asterisco rosso sull'etichetta in automatico; legenda "* campo obbligatorio" nel piè di pagina.
- **Controlli condizionati** da un altro campo o da dati mancanti: disabilitati (`data-attiva-se`, `:blocca`), mai nascosti, con accanto `<x-info testo="…">` che spiega come attivarli.
- Select lunghe (docenti, materie, classi nelle viste dell'orario): `data-ricerca` per la select con ricerca; elenchi in ordine alfabetico.
- Input/select/textarea: lo stile base è in `app.css` (bordo, padding, anello di focus di marca): non servono classi sui singoli campi.

## Azioni riga tabella

- "Modifica": link testuale `text-gray-600 hover:text-gray-900 underline`
- Eliminazione: solo da selezione multipla, niente pulsante per riga
- Niente icone senza testo (coerente con la regola icon-only-senza-label del checklist UX)

## Notifiche

- Conferme, errori di validazione ed esiti sono **toast** in alto a destra (10 secondi, pausa col mouse, × per chiuderli): `data-flash` dal server, `mostraToast()` dal JS. Niente caselle colorate nella pagina.
- Eccezioni persistenti: pannello avvisi dell'orario (`avvisi_orario`) ed elenco degli errori di import CSV.
