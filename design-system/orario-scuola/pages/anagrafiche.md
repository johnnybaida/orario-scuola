# Pagine anagrafica (index / create / edit)

> Override di MASTER.md per le pagine CRUD (sedi, aule, discipline, quadri orari, docenti, classi, cattedre, vincoli).

## Struttura index

1. `<h1>` + eventuale azione primaria (`Nuovo X`) in alto a destra, visibile solo con `@can('gestisci-...')`
2. `<x-guida>` — una spiegazione breve di cosa rappresenta l'entità e come si collega alle altre
3. Tabella in un contenitore `bg-white border border-gray-200 rounded-lg overflow-x-auto` (mai `overflow-hidden`: tronca i dati su schermi stretti invece di scorrere)
4. Se l'elenco può crescere oltre ~50 righe: `->paginate(50)->withQueryString()` + `{{ $items->links() }}`

## Struttura form (create/edit)

- Un solo `<form>` in una card `bg-white border border-gray-200 rounded-lg p-6`
- Campi condivisi tra create/edit in una partial `_form.blade.php`
- Pulsante di submit: classe `bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer`
- Input/select/textarea: `focus:border-primary focus:ring-primary` (non grigio: è il ring di focus di marca)

## Azioni riga tabella

- "Modifica": link testuale `text-gray-600 hover:text-gray-900 underline`
- Eliminazione: solo da selezione multipla (checkbox `.js-sel` per riga + `<x-barra-selezione>`), niente pulsante per riga
- Creazione/modifica: in modale (`data-modale`), form a due colonne con "Salva" in basso a destra
- Niente icone senza testo (coerente con la regola icon-only-senza-label del checklist UX)
