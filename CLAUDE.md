# CLAUDE.md — Orario Scuola Media

Applicativo web per **generare e gestire l'orario settimanale** di una scuola secondaria di I grado (una sola scuola per installazione), con **vincoli configurabili**, **modifica manuale**, **assenze e sostituzioni**.

La specifica funzionale completa è in `docs/analisi-orario-scuola-media.md`: è la **fonte di verità**. Questo file ne riassume le parti necessarie per sviluppare. In caso di dubbio o conflitto, vale l'analisi; se l'analisi non copre il caso, **chiedi** invece di decidere.

---

## Stack

| Componente | Scelta |
|---|---|
| Backend | Laravel 13 (PHP ≥ 8.3) |
| Database | MariaDB |
| Frontend | Blade + **JavaScript vanilla** (nessun framework JS, niente jQuery); bundling con Vite dello skeleton Laravel |
| Code | Laravel queue, driver `database` |
| Solver | Python 3 + OR-Tools CP-SAT, script locale in `solver/` invocato da un job |
| Auth | Solo account locali (niente SSO) |
| Export | PDF ed Excel |
| Test | Test runner di default di Laravel; `pytest` per il solver |

Non aggiungere dipendenze (Composer, npm, pip) senza chiedere. Per PDF/Excel proponi il pacchetto e verificane la compatibilità con Laravel 13 prima di installarlo.

---

## Comandi

```bash
# setup
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
python3.11 -m venv solver/.venv && solver/.venv/bin/pip install -r solver/requirements.txt

# sviluppo
php artisan serve
npm run dev
php artisan queue:work          # necessario per la generazione dell'orario

# test
php artisan test
solver/.venv/bin/pytest solver/tests
```

Se un comando non esiste ancora o cambia, aggiorna questa sezione.

---

## Struttura

```
app/
  Models/                 # entità di dominio (nomi in italiano, vedi Convenzioni)
  Http/Controllers/
  Services/
    Solver/               # ProblemBuilder (DB → JSON), SolverRunner (Process), ResultImporter (JSON → DB)
    Validation/           # pre-validazione prima del solving
    Substitution/         # proposta sostituzioni
    Export/               # PDF, Excel
  Jobs/GenerateTimetable.php
  Constraints/            # catalogo tipi di vincolo: definizione, validazione parametri, descrizione testuale
resources/
  views/
  js/                     # moduli ES vanilla, uno per pagina/componente (es. timetable-editor.js)
solver/
  solver.py               # entrypoint: legge JSON da stdin, scrive JSON su stdout
  constraints/            # un modulo per tipo di vincolo
  tests/
docs/
  analisi-orario-scuola-media.md
```

---

## Convenzioni

- **Nomi di dominio in italiano** (modelli, tabelle, colonne) perché i termini non hanno equivalenti precisi: `Docente`, `Classe`, `Disciplina`, `Cattedra`, `Lezione`, `Sostituzione`, `Vincolo`. Codice tecnico, metodi generici e commenti di servizio in inglese.
- Tabelle al plurale snake_case (`docenti`, `classi`, `cattedre`); specificare `$table` nei modelli dove la pluralizzazione inglese sbaglia.
- UI e messaggi all'utente in italiano.
- Parametri dei vincoli in colonna JSON, validati dalla definizione del tipo in `app/Constraints/`.
- Logica di dominio nei Service, non nei controller.
- JavaScript: moduli ES, niente variabili globali, `fetch` verso endpoint JSON.
- Ogni modifica a orario, vincoli e sostituzioni va nell'audit log.
- Creazione/modifica in modale: link `data-modale` (`resources/js/modale.js`); con `X-Requested-With` il layout rende solo `@yield('contenuto')`. L'eliminazione è solo a selezione multipla (`.js-sel` + `<x-barra-selezione>`), non per riga. Niente campi liberi per valori censiti altrove: usa select.
- Utenze (solo amministratore) in `/utenze`.
- Ogni pagina principale ha una mini guida con il componente `<x-guida>` (vedi `resources/views/components/guida.blade.php`).
- Gli esiti (errori/avvisi) delle modifiche manuali all'orario restano visibili in un pannello persistente (tabella `avvisi_orario`) finché non vengono azzerati esplicitamente: non usare `alert()` JS per questo.

---

## Regole di dominio essenziali

### Dati
- **Alunni non censiti.** Per classe solo `n_alunni`; per gruppo solo `n_partecipanti`.
- **Sostegno** (implementato, anticipato rispetto alla Fase 3 originale su richiesta esplicita): per classe, fabbisogni anonimi in `fabbisogni_sostegno` (`codice_anonimo`, es. `1B-S1`, + ore settimanali + `docente_unico` = S4). Docenti assegnati in `assegnazioni_sostegno` (docente + classe + ore). Conteggio in `Impostazioni.conteggio_sostegno` (default istituto) con override opzionale per classe (`Classe.conteggio_sostegno`, nullable = eredita il default). Le ore di sostegno sono **compresenze**, modellate nel solver (`solver/sostegno.py`) e persistite in `compresenze_sostegno` dopo la generazione. **Non implementati**: S1 (discipline preferite), S2 (discipline escluse), S3 (distribuzione minima su più giorni) come vincoli configurabili dedicati.
- **DADA** (implementato, non nella specifica originale): `aule.tipo` e `discipline.tipo_aula_richiesto` sono stringhe libere, non enum. Oltre ai tipi base, una scuola può censire un'aula dedicata a una disciplina con un tipo a piacere (es. `dada_italiano`) e collegarla dalla scheda della disciplina; riusa il meccanismo esistente di capienza/scelta aula (nessuna modifica al solver necessaria). In UI il tipo aula si sceglie da select: "DADA · disciplina" crea il tipo `dada_{codice}` e lo collega alla disciplina; in DADA le classi non hanno aula base (si spostano gli alunni).
- **Scansione oraria unica di istituto**, ereditata da tutte le classi. La durata dell'ora è unica e configurabile (default 50'), con numero e posizione degli intervalli. Se la durata è < 60' il sistema calcola solo il report dei minuti da recuperare.
- **Docenti**: tipo posto (comune, sostegno, potenziamento, IRC, strumento), regime (tempo pieno/part-time), ore dovute (cattedra intera = 18), indisponibilità. Per i COE si gestiscono solo le indisponibilità.
- **Gruppi interclasse** per seconda lingua articolata, alternativa IRC, LEL (latino opzionale), strumento: le classi coinvolte devono essere compatibili nello stesso slot.
- Fuori perimetro: registro elettronico, valutazioni, stipendi, educatori/OSA, notifiche, SSO, multi-scuola.

### Vincoli rigidi di sistema (sempre attivi)
H1 classe max una lezione per slot (salvo compresenze/gruppi paralleli) · H2 docente in un solo posto per slot · H3 capienza aula · H4 ore per disciplina esatte · H5 tutti gli slot del tempo scuola coperti · H6 indisponibilità docente · H7 tipo aula richiesto · H8 tempi di spostamento tra sedi · H9 compatibilità gruppi interclasse · H10 lezioni bloccate non si spostano.

### Vincoli configurabili
Catalogo parametrico (codici D*, T*, C*, S*, R*, F* — dettaglio nel §8 dell'analisi). Ogni vincolo ha: `tipo`, `ambito` (globale/classe/docente/disciplina/aula + ids), `parametri`, `severita` (`rigido` | `preferenziale`), `peso` (1–100), `attivo`. Il vincolo più specifico prevale su quello globale. I blocchi a cavallo dell'intervallo sono ammessi di default (vietabili con D7).

### Generazione
- Sempre asincrona (job in coda) con time limit, avanzamento via polling e annullamento.
- **Seed** registrato per ogni generazione, così il risultato è riproducibile.
- Si può fare una generazione parziale mantenendo bloccato il resto.
- La **pre-validazione** avviene prima del solving (capacità docenti/aule, coerenza quadri orari-cattedre, vincoli contraddittori).
- In caso di infattibilità si restituisce una **diagnostica leggibile** (quali vincoli o risorse sono in conflitto), mai un errore generico.

### Sostituzioni
Proposta automatica secondo l'ordine configurabile (default §10.2 dell'analisi): compresenza → permesso breve da recuperare → ora a disposizione/potenziamento → docente della classe → stessa disciplina → ore eccedenti → sostegno (a scelta del referente) → divisione classe. Con contatori di equità. Il foglio giornaliero è in PDF e consultabile in app.

---

## Contratto PHP ↔ solver

`solver.py` legge un JSON su stdin e scrive un JSON su stdout; codice di uscita ≠ 0 solo per errori tecnici (l'infattibilità è un esito valido).

```jsonc
// input
{
  "seed": 12345,
  "time_limit_s": 120,
  "slots": [{ "id": 1, "giorno": 1, "ordine": 1, "intervallo_dopo": false }],
  "aule": [{ "id": 1, "tipo": "palestra", "capacita": 2 }],
  "docenti": [{ "id": 1, "indisponibili": [3, 4] }],
  "classi": [{ "id": 1, "slots_attivi": [1, 2, 3] }],
  "lezioni": [{ "id": 1, "classi": [1], "docenti": [1], "disciplina": "ITA",
               "durata": 1, "tipo_aula": null, "gruppo_parallelo": null,
               "bloccata_slot": null }],
  "sostegno": [{ "classe": 1, "fabbisogni": [{ "codice": "1B-S1", "ore": 9 }],
                 "docenti": [{ "id": 7, "ore": 9 }], "conteggio": "per_alunno" }],
  "vincoli": [{ "tipo": "D1_BLOCCO_MIN_CONSECUTIVO", "ambito": {...},
                "parametri": {...}, "severita": "rigido", "peso": null }]
}
// output
{
  "stato": "ottimo | fattibile | infattibile | timeout",
  "punteggio": 42,
  "assegnazioni": [{ "lezione": 1, "slot": 3, "aula": 1 }],
  "compresenze_sostegno": [{ "docente": 7, "classe": 1, "slot": 5, "codice": "1B-S1" }],
  "violazioni_soft": [{ "vincolo_id": 12, "conteggio": 2, "dettaglio": "..." }],
  "diagnostica": ["..."]
}
```

Qualsiasi modifica al contratto va applicata in modo coordinato su `ProblemBuilder`, `ResultImporter`, `solver.py` e sui test di entrambi i lati.

---

## Roadmap (fase corrente: **MVP**)

| Fase | Contenuto |
|---|---|
| **MVP** | Anagrafiche + import CSV, scansione oraria di istituto, quadri orari, cattedre manuali e proposta automatica, vincoli H1–H10 + D1, D3, D6, T1, T2, T3, generazione con seed, editor griglia, export PDF — **completo** |
| Anticipato | Sostegno (fabbisogni, assegnazioni, compresenze, S4 docente unico) e DADA (aula per disciplina), su richiesta esplicita |
| Fase 2 | Assenze e sostituzioni con proposta automatica, recupero permessi, versioni e diff |
| Fase 3 | Gruppi interclasse, S1–S3 (vincoli sostegno su discipline/distribuzione), multi-sede e indisponibilità COE |
| Fase 4 | Varianti multiple e confronto, rilassamento guidato dei vincoli, export Excel |

Non anticipare funzionalità di fasi successive; se servono predisposizioni nel modello dati, segnalale.

---

## Come lavorare

- Modifica solo ciò che serve al task; niente refactoring non richiesti.
- Prima di scelte architetturali non coperte da qui o dall'analisi: **fermati e chiedi**.
- Ogni nuovo tipo di vincolo si implementa su entrambi i lati (definizione in `app/Constraints/` + modulo in `solver/constraints/`), con un test PHP di validazione e un test pytest con un caso fattibile e uno infattibile.
- Scrivi i test per pre-validazione, solver e proposta sostituzioni; usa i casi limite del §16 dell'analisi come fixture.
- Migrazioni sempre reversibili; seeder con una scuola di esempio realistica (circa 15 classi, 40 docenti, quadro a 30 ore).
- Non modificare `docs/analisi-orario-scuola-media.md` senza chiedere; se una decisione la cambia, proponi l'aggiornamento.
