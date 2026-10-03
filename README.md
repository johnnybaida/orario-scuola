# Orario Scuola Media

Applicativo web per generare e gestire l'orario settimanale di una scuola secondaria di I grado: anagrafiche, cattedre, vincoli configurabili, generazione automatica (OR-Tools CP-SAT), editor a griglia con drag&drop ed export PDF.

Cosa fa: anagrafiche (sedi, aule, discipline, quadri orari, docenti, classi, cattedre) con import CSV, sostegno e didattica DADA, vincoli configurabili, generazione automatica asincrona con seed riproducibile, editor a griglia, export PDF (griglie e tabellone generale su un foglio), utenze con ruoli, dashboard operativa e **guida in-app** (pulsante *Aiuto* o tasto **F1**, in funzione del ruolo).

La specifica funzionale completa è in [`docs/analisi-orario-scuola-media.md`](docs/analisi-orario-scuola-media.md); le convenzioni di sviluppo sono in [`CLAUDE.md`](CLAUDE.md).

---

## Requisiti

In alternativa a tutto quanto segue basta **Docker** con Docker Compose: vedi [Avvio con Docker](#avvio-con-docker).

| Componente | Versione | Note |
|---|---|---|
| PHP | ≥ 8.3 | con estensioni `pdo_mysql`, `mbstring`, `xml`, `bcmath` (incluse in una installazione PHP standard) |
| Composer | 2.x | |
| Node.js | ≥ 20 | per Vite/Tailwind |
| MariaDB / MySQL | 10.x / 8.x | un database vuoto, es. `orario_scuola` |
| Python | 3.11 | per il solver OR-Tools (CP-SAT) |

Il progetto non richiede Apache/Nginx: `php artisan serve` basta per lo sviluppo. Se usi MAMP/MAMP PRO per il database, assicurati che PHP CLI (quello usato per i comandi sotto) abbia l'estensione `pdo_mysql` — non è necessario che sia lo stesso PHP imacchettato con MAMP.

---

## Setup iniziale

```bash
# dipendenze PHP e JS
composer install
npm install

# configurazione
cp .env.example .env
php artisan key:generate
```

Apri `.env` e imposta le credenziali del database (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) prima di continuare.

```bash
# schema + dati di esempio (15 classi, 40 docenti, quadro a 30 ore)
php artisan migrate --seed

# ambiente del solver Python (OR-Tools CP-SAT)
python3.11 -m venv solver/.venv
solver/.venv/bin/pip install -r solver/requirements.txt
```

---

## Avvio con Docker

Con Docker (e Docker Compose) si avvia tutto con un comando, senza installare PHP, Node, Python o il database:

```bash
docker compose up -d --build
```

Poi apri <http://localhost:8080> (la porta si cambia con `APP_PORT`). Al primo avvio il container: crea la chiave dell'applicazione, esegue le migrazioni, carica la **scuola di esempio** (≈15 classi, 40 docenti) e avvia il worker di coda. Accesso iniziale: `amministratore@scuola.test` / `password`: **cambia la password** (o impostala subito con `ADMIN_PASSWORD`, vedi sotto) prima di usarlo su un server raggiungibile da altri. Gli altri utenti di prova sono in [Accessi di prova](#accessi-di-prova-seed).

Servizi: `db` (MariaDB 11) e `app` (PHP + FrankenPHP con gli assets compilati e il solver Python). I dati stanno in due volumi, `dbdata` (database) e `storage` (chiave, log, file), e sopravvivono ai riavvii.

| Variabile (file `.env` accanto a `compose.yaml`) | Significato | Predefinito |
|---|---|---|
| `APP_PORT` | porta sul computer | `8080` |
| `APP_URL` | indirizzo pubblico dell'applicazione | `http://localhost:8080` |
| `ADMIN_PASSWORD` | password dell'amministratore di esempio, impostata a ogni avvio | `password` |
| `SEED_ESEMPIO` | `1` = carica la scuola di esempio al primo avvio, `0` = non carica nulla | `1` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | password del database | `orario`, `root` |
| `APP_KEY` | chiave dell'applicazione (se vuota se ne genera una e si conserva nel volume) | vuota |

Comandi utili:

```bash
docker compose logs -f app      # log dell'applicazione
docker compose down             # ferma (i dati restano)
docker compose down -v          # ferma e CANCELLA database e file
docker compose up -d --build    # dopo un aggiornamento del codice
```

---

## Sviluppo

Servono **tre processi** in parallelo (tre terminali, o un multiplexer):

```bash
php artisan serve        # applicazione: http://127.0.0.1:8000
npm run dev              # build Vite con hot reload di CSS/JS
php artisan queue:work   # worker di coda: necessario per generare l'orario (vedi sotto)
```

Senza worker attivo una generazione resta "in coda" a tempo indeterminato. Il worker si può anche **avviare e fermare dall'interfaccia** (pagina *Genera orario* e dashboard; l'arresto è sicuro, termina il job in corso) e **parte da solo** premendo *Avvia generazione*: in sviluppo il terzo terminale è quindi facoltativo.

### Accessi di prova (seed)

Password per tutti: `password`.

| Email | Ruolo |
|---|---|
| `amministratore@scuola.test` | Amministratore |
| `referente_orario@scuola.test` | Referente Orario (gestisce anagrafiche, vincoli, generazione) |
| `referente_sostituzioni@scuola.test` | Referente Sostituzioni |
| `segreteria@scuola.test` | Segreteria (gestisce docenti/classi) |
| `docente@scuola.test` | Docente |
| `ds@scuola.test` | Dirigente Scolastico |

---

## Test

```bash
php artisan test                    # test PHP (Feature + Unit)
solver/.venv/bin/pytest solver/tests   # test del solver Python
```

---

## Struttura del progetto

```
app/
  Models/                 # entità di dominio (nomi in italiano)
  Http/Controllers/
  Http/Requests/          # validazione dei form
  Support/                # ruoli/permessi, mappa pagina → sezione della guida
  Constraints/            # catalogo vincoli configurabili (D1, D3, D6, T2, T3)
  Services/
    Solver/               # ProblemBuilder, SolverRunner, ResultImporter
    Validation/           # pre-validazione prima del solving (con link per correggere)
    Editor/               # spostamento/scambio lezioni nella griglia
    Export/               # export PDF
    QueueWorker.php       # avvio/arresto del worker di coda dall'interfaccia
    SincronizzaRighe.php  # salvataggio delle righe ripetibili dei form
  Jobs/GenerateTimetable.php
resources/
  views/                  # Blade; components/ con i pezzi riusabili (toast, barre, righe ripetibili, ...)
  js/                     # moduli ES vanilla (nessun framework JS)
solver/
  solver.py               # entrypoint CP-SAT: JSON stdin -> JSON stdout
  constraints/            # un modulo per tipo di vincolo
  tests/
docs/
  analisi-orario-scuola-media.md   # specifica funzionale
  guida-utente.md                  # manuale mostrato nel pannello Aiuto
design-system/            # design system dell'interfaccia
docker/, Dockerfile, compose.yaml  # avvio con Docker
```

Per le convenzioni di codice, l'interfaccia, i permessi, il contratto PHP↔solver e la roadmap delle fasi successive, vedi [`CLAUDE.md`](CLAUDE.md). Il manuale per gli utenti è in [`docs/guida-utente.md`](docs/guida-utente.md) (si aggiorna insieme alle funzioni).
