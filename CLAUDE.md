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
| Deploy | Docker Compose: `app` (FrankenPHP: PHP + web server, assets compilati, solver Python) e `db` (MariaDB); vedi `compose.yaml` e `Dockerfile` |
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
php artisan queue:work          # necessario per la generazione; l'app lo avvia/ferma da "Genera orario" e lo avvia da sola con "Avvia generazione"

# tutto su Docker (app + database + worker, con la scuola di esempio): http://localhost:8080
docker compose up -d --build
docker compose down -v          # ferma e cancella i dati
# per chi non è sviluppatore: doppio clic su Avvia-Orario-Scuola.bat (Windows) o .command (Mac); istruzioni nel README

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
  Http/Requests/          # validazione dei form
  Support/                # Ruoli (ruoli e gruppi di permessi), Guida (mappa pagina → sezione della guida)
  Services/
    Solver/               # ProblemBuilder (DB → JSON), SolverRunner (Process), ResultImporter (JSON → DB)
    Validation/           # PreValidator: problemi() con link per correggerli, esegui() solo i messaggi
    Editor/               # spostamento/scambio lezioni nella griglia
    Export/               # PDF (griglie classe/docente, tabellone generale); Excel in Fase 4
    Substitution/         # proposta sostituzioni (Fase 2)
    QueueWorker.php       # avvia/ferma il worker di coda dall'interfaccia (PID file in storage/app)
    SincronizzaRighe.php  # salva le righe ripetibili dei form (id = aggiorna, senza id = crea, assenti = elimina)
  Jobs/GenerateTimetable.php
  Constraints/            # catalogo tipi di vincolo: definizione, validazione parametri, descrizione testuale
resources/
  views/                  # una cartella per risorsa; components/: guida, info, barra-tabella, barra-selezione,
                          #   barra-salvataggio, righe-ripetibili
  js/                     # moduli ES vanilla, uno per componente: modale, toast, selezione-multipla, form-modifica,
                          #   condizioni, select-ricerca, guida-pannello, editor-griglia, ...
solver/
  solver.py               # entrypoint: legge JSON da stdin, scrive JSON su stdout
  constraints/            # un modulo per tipo di vincolo
  tests/
docs/
  analisi-orario-scuola-media.md   # specifica funzionale (fonte di verità)
  guida-utente.md                  # manuale mostrato nel pannello Aiuto (dipende dal ruolo)
design-system/            # design system dell'interfaccia (MASTER.md + regole per tipo di pagina)
docker/                   # entrypoint, Caddyfile e script di avvio del container; vedi anche Dockerfile e compose.yaml
Avvia-/Ferma-Orario-Scuola.bat|.command   # launcher a doppio clic per chi non è sviluppatore (.bat con fine riga CRLF: vedi .gitattributes)
```

---

## Convenzioni

- **Nomi di dominio in italiano** (modelli, tabelle, colonne) perché i termini non hanno equivalenti precisi: `Docente`, `Classe`, `Disciplina`, `Cattedra`, `Lezione`, `Sostituzione`, `Vincolo`. Codice tecnico, metodi generici e commenti di servizio in inglese.
- Tabelle al plurale snake_case (`docenti`, `classi`, `cattedre`); specificare `$table` nei modelli dove la pluralizzazione inglese sbaglia.
- UI e messaggi all'utente in italiano.
- Parametri dei vincoli in colonna JSON, validati dalla definizione del tipo in `app/Constraints/`.
- Logica di dominio nei Service, non nei controller.
- JavaScript: moduli ES, niente variabili globali, `fetch` verso endpoint JSON.
- Ogni modifica va nell'audit log. I modelli di anagrafica, vincoli, utenze, cattedre, sostegno e generazioni usano il trait `App\Models\Concerns\Auditable` (creazione/modifica/eliminazione con valori prima/dopo, nome leggibile in `etichetta`, password come `***`); un nuovo modello di dominio deve usarlo. Gli eventi si generano solo con le operazioni per modello: **non** usare `Model::query()->update()/delete()` in blocco sui modelli auditati (usa `->get()->each->...`, come `SincronizzaRighe`). Le operazioni senza evento (generazione, cambi di stato, duplicazioni) si registrano con `AuditLog::registra()`; i seeder girano dentro `AuditLog::senza()`. Non sono registrati i `sync()` di pivot (sedi, indisponibilità, slot attivi). Pagina `/audit` (gate `approva-orari`), sola lettura; il registro non si modifica né si cancella dall'app.

### Interfaccia

- Docenti e classi si creano e si modificano su pagina intera (troppe informazioni per una modale); le altre anagrafiche in modale.
- Creazione/modifica in modale: link `data-modale` (`resources/js/modale.js`); con `X-Requested-With` il layout rende solo `@yield('contenuto')`. L'eliminazione è solo a selezione multipla (`.js-sel` + `<x-barra-selezione>`), non per riga. Niente campi liberi per valori censiti altrove: usa select.
- I form prendono sempre tutta la larghezza disponibile (modale o pagina), con i campi su due colonne: classe `.form-colonne` sulla scheda (nelle modali è automatico). Niente `max-w-*` sui form.
- Pagine e modali di modifica: un solo form con un solo Salva/Annulla fisso in basso a destra (`<x-barra-salvataggio>` nelle pagine, piè di pagina nelle modali). Le liste ripetibili (cattedre, righe del quadro, fabbisogni/assegnazioni di sostegno) si gestiscono in JS con `<x-righe-ripetibili>` (`resources/js/form-modifica.js`) e si salvano con `App\Services\SincronizzaRighe` (id = aggiorna, senza id = crea, assenti = elimina).
- Controlli che dipendono da un altro campo o da dati mancanti non si nascondono: si disabilitano (`data-attiva-se="#campo=valore"`, `<x-righe-ripetibili :blocca="…">`, `disabled`) con accanto `<x-info testo="…">` che spiega come attivarli.
- Le notifiche all'utente (conferme, errori di validazione, esiti) sono toast che spariscono dopo 10 secondi: il server le espone con `<div data-flash="successo|errore|avviso" hidden>` nel layout, il JS con `mostraToast()` da `resources/js/toast.js`. Restano fuori i pannelli persistenti (avvisi dell'orario, errori di import CSV).
- Gli esiti (errori/avvisi) delle modifiche manuali all'orario restano visibili in un pannello persistente (tabella `avvisi_orario`) finché non vengono azzerati esplicitamente: non usare `alert()` JS per questo.
- Ogni pagina principale ha una mini guida con il componente `<x-guida>` (vedi `resources/views/components/guida.blade.php`).
- Guida utente: unico file `docs/guida-utente.md`, mostrato nel pannello di aiuto (pulsante Aiuto o F1, `GuidaController` + `resources/js/guida-pannello.js`); una sezione `##` = una voce del menu. Aggiornalo quando cambia una funzione visibile all'utente. La guida dipende dal ruolo: `<!-- sezione: GATE -->` sotto un `##` limita l'intera sezione, `<!-- permesso: GATE -->…<!-- /permesso -->` un blocco (GATE = consulta, gestisci-anagrafica, gestisci-docenti-classi, gestisci-utenze); `{ruolo}` diventa il ruolo dell'utente.
- Le voci del menu si mostrano solo a chi ha il permesso giusto (gate `consulta`, `gestisci-utenze`, …, vedi `layouts/app.blade.php`): menu, pulsanti e guida seguono sempre il ruolo.
- I campi obbligatori hanno l'attributo `required` (anche impostato via JS): l'asterisco rosso compare da solo sull'etichetta (`label:has(+ [required])` in `app.css`).
- La dashboard (`DashboardController`) mostra i controlli prima di generare (`PreValidator::problemi()`), worker e ultima generazione, ultimo orario, carico dei docenti e percorso di avvio; il ruolo `docente` vede solo il benvenuto.

---

## Regole di dominio essenziali

### Dati
- **Alunni non censiti.** Per classe solo `n_alunni`; per gruppo solo `n_partecipanti`.
- **Sostegno** (implementato, anticipato rispetto alla Fase 3 originale su richiesta esplicita): per classe, fabbisogni anonimi in `fabbisogni_sostegno` (`codice_anonimo`, es. `1B-S1`, + ore settimanali + `docente_unico` = S4). Docenti assegnati in `assegnazioni_sostegno` (docente + classe + ore). Conteggio in `Impostazioni.conteggio_sostegno` (default istituto) con override opzionale per classe (`Classe.conteggio_sostegno`, nullable = eredita il default). Le ore di sostegno sono **compresenze**, modellate nel solver (`solver/sostegno.py`) e persistite in `compresenze_sostegno` dopo la generazione. **Non implementati**: S1 (discipline preferite), S2 (discipline escluse), S3 (distribuzione minima su più giorni) come vincoli configurabili dedicati.
- **DADA** (implementato, non nella specifica originale): `aule.tipo` e `discipline.tipo_aula_richiesto` sono stringhe libere, non enum. Oltre ai tipi base, una scuola può censire un'aula dedicata a una disciplina con un tipo a piacere (es. `dada_italiano`) e collegarla dalla scheda della disciplina; riusa il meccanismo esistente di capienza/scelta aula (nessuna modifica al solver necessaria). In UI il tipo aula si sceglie da select: "DADA · disciplina" crea il tipo `dada_{codice}` e lo collega alla disciplina; in DADA le classi non hanno aula base (si spostano gli alunni).
- **Scansione oraria unica di istituto**, ereditata da tutte le classi: da lunedì a venerdì, ore 1ª–6ª al mattino e 7ª–9ª al pomeriggio **ogni giorno** (migrazione e `SlotSeeder`); ciascuna classe attiva solo i propri slot (`classe_slot`, il numero deve coincidere con le ore del quadro orario). La durata dell'ora è unica e configurabile (default 50'), con numero e posizione degli intervalli. Se la durata è < 60' il sistema calcola solo il report dei minuti da recuperare. Le ore dopo l'ultima usata non si mostrano nelle griglie e nei PDF. Orari di inizio/fine di ogni ora e ricreazioni (`intervallo_dopo`, anche più d'una) si impostano nella pagina **Scansione oraria** (`ScansioneOrariaController`, uguali per tutti i giorni, audit log); i PDF mostrano gli orari delle ore e le ricreazioni (riga «Ricreazione hh:mm-hh:mm» nelle griglie, legenda nel tabellone).
- **Docenti**: tipo posto (comune, sostegno, potenziamento, IRC, strumento), regime (tempo pieno, part-time orizzontale/verticale/misto), contratto, ore dovute (cattedra intera = 18), indisponibilità. Per i COE si gestiscono solo le indisponibilità. **Contratto, regime, COE, classi di concorso, `n_alunni` e il flag `compresenza` delle cattedre sono oggi dati informativi: il solver non li usa**; i giorni/ore di assenza si impongono con le indisponibilità.
- **Rientri pomeridiani** (tempo prolungato): scelti per giorno e per classe nel form della classe (spuntano le ore 7ª–9ª del giorno negli slot attivi); in modifica la griglia degli slot prevale.
- **Gruppi interclasse** per seconda lingua articolata, alternativa IRC, LEL (latino opzionale), strumento: le classi coinvolte devono essere compatibili nello stesso slot.
- Fuori perimetro: registro elettronico, valutazioni, stipendi, educatori/OSA, notifiche, SSO, multi-scuola.

### Accessi e permessi
Account locali (`/utenze`, solo amministratore); ruolo e docente collegato (solo per `docente`). Ruoli (`App\Support\Ruoli`) e gate (`AppServiceProvider`):

| Ruolo | Gate |
|---|---|
| `amministratore` | `consulta`, `gestisci-anagrafica`, `gestisci-docenti-classi`, `gestisci-utenze`, `approva-orari` |
| `referente_orario` | `consulta`, `gestisci-anagrafica`, `gestisci-docenti-classi` |
| `segreteria` | `consulta`, `gestisci-docenti-classi` (docenti e classi) |
| `ds` | `consulta`, `approva-orari` |
| `referente_sostituzioni` | `consulta` (sola lettura) |
| `docente` | nessuno: vede solo dashboard e guida |

Non si elimina la propria utenza né si toglie a se stessi il ruolo di amministratore.

### Worker di coda e orari
- Il worker (`queue:work`) lo gestisce `App\Services\QueueWorker` (processo figlio con PID file): "Avvia/Ferma" in **Genera orario** e dashboard; l'arresto è graceful (`queue:restart`, stato "in arresto"); **Avvia generazione** lo avvia da solo se è fermo (non con coda `sync`). Su Docker parte al boot del container.
- **Stati dell'orario** (`App\Support\StatiOrario`): `bozza` → `in_revisione` → `approvato` → `pubblicato` → `archiviato`. Solo la bozza è modificabile (`Orario::modificabile()`: l'editor risponde 422 e la griglia è in sola lettura altrimenti). Inviare in revisione o rimandare in bozza una revisione spetta a `gestisci-anagrafica`; approvare, pubblicare, archiviare e riaprire un approvato a `approva-orari`. Una sola versione `pubblicato` per periodo (pubblicando, la precedente va in `archiviato`). **Duplica** (`gestisci-anagrafica`) copia lezioni e compresenze in una nuova bozza (versione successiva). Si elimina solo da `bozza` o `archiviato`. Stati e duplicazioni vanno nell'audit log.
- **Annulla/Ripeti** su più livelli nell'editor (`EditorLezione::annulla/ripeti`): la cronologia sta nella tabella `modifiche_orario` (tipo spostamento|scambio|cambio_cattedra|blocco, stato prima/dopo, flag `annullata` = stack «ripeti»), separata dall'audit log, che non perde righe (gli annullamenti si aggiungono come `annullamento`/`ripristino`). Una nuova modifica cancella lo stack «ripeti»; Ctrl/Cmd+Z e Ctrl/Cmd+Maiusc+Z in `editor-griglia.js`. Solo in bozza; la duplicazione non copia la cronologia.
- Un orario nasce in stato `bozza`; la tabella **Orari** permette di consultarlo, esportare i PDF (griglia classe/docente, tabellone generale su un foglio A3: classi in riga, giorni a larghezza uguale, sostegno visibile) ed eliminarlo (audit log; le generazioni restano come storico).
- L'orario dipende ancora dai censimenti (cattedre, docenti, ...): eliminarli elimina a cascata le lezioni. Valutata e **rimandata** l'idea di orario come snapshot con approvazione (vedi Roadmap).

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
| Anticipato | Su richiesta esplicita: sostegno (fabbisogni, assegnazioni, compresenze, S4 docente unico), DADA (aula per disciplina), utenze e ruoli, dashboard operativa, guida utente in-app (F1, per ruolo), gestione del worker da interfaccia, Docker Compose, stati e approvazione degli orari con duplicazione |
| Fase 2 | Assenze e sostituzioni con proposta automatica, recupero permessi, versioni e diff |
| Fase 3 | Gruppi interclasse, S1–S3 (vincoli sostegno su discipline/distribuzione), multi-sede e indisponibilità COE |
| Fase 4 | Varianti multiple e confronto, rilassamento guidato dei vincoli, export Excel |

Non anticipare funzionalità di fasi successive; se servono predisposizioni nel modello dati, segnalale.

**Idee valutate e rimandate**: orario come *snapshot* indipendente dai censimenti (copia di classe, docente, disciplina, aula e slot su ogni lezione, FK non a cascata) con impronta dei dati per segnalare che i censimenti sono cambiati; oggi eliminare un docente o una classe elimina a cascata le lezioni anche degli orari approvati o pubblicati. Il confronto (diff) tra versioni resta in Fase 2.

---

## Come lavorare

- **Non modificare mai il file `.env`** (né `.env.local`, `.env.production`, ...): contiene credenziali e la chiave dell'applicazione e non è recuperabile da git. Niente `cp`/`mv`/`rm`/redirect/`sed -i` su di esso e niente `php artisan key:generate` senza `--show`: se serve un valore diverso, chiedilo all'utente. Per le prove di script usa percorsi temporanei o variabili d'ambiente. L'impedimento è anche tecnico: `.claude/settings.json` nega le modifiche con gli strumenti di scrittura e `.claude/hooks/proteggi-env.py` blocca i comandi di shell che toccano `.env` (la lettura resta consentita; `.env.example` è modificabile).
- Modifica solo ciò che serve al task; niente refactoring non richiesti.
- Prima di scelte architetturali non coperte da qui o dall'analisi: **fermati e chiedi**.
- Ogni nuovo tipo di vincolo si implementa su entrambi i lati (definizione in `app/Constraints/` + modulo in `solver/constraints/`), con un test PHP di validazione e un test pytest con un caso fattibile e uno infattibile.
- Scrivi i test per pre-validazione, solver e proposta sostituzioni; usa i casi limite del §16 dell'analisi come fixture.
- Migrazioni sempre reversibili; seeder con una scuola di esempio realistica (circa 15 classi, 40 docenti, quadro a 30 ore).
- Dopo ogni modifica visibile all'utente aggiorna `docs/guida-utente.md` (con i marcatori di ruolo) e, se cambiano struttura o convenzioni, questo file e `README.md`.
- Non modificare `docs/analisi-orario-scuola-media.md` senza chiedere; se una decisione la cambia, proponi l'aggiornamento.
