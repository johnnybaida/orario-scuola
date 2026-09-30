# Orario Scuola Media

Applicativo web per generare e gestire l'orario settimanale di una scuola secondaria di I grado: anagrafiche, cattedre, vincoli configurabili, generazione automatica (OR-Tools CP-SAT), editor a griglia con drag&drop ed export PDF.

La specifica funzionale completa è in [`docs/analisi-orario-scuola-media.md`](docs/analisi-orario-scuola-media.md); le convenzioni di sviluppo sono in [`CLAUDE.md`](CLAUDE.md).

---

## Requisiti

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

## Sviluppo

Servono **tre processi** in parallelo (tre terminali, o un multiplexer):

```bash
php artisan serve        # applicazione: http://127.0.0.1:8000
npm run dev              # build Vite con hot reload di CSS/JS
php artisan queue:work --queue=high,default   # worker code: necessario per generare l'orario
```

Senza `queue:work` attivo, avviare una generazione dell'orario resta bloccato in stato "in coda" a tempo indeterminato.

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
  Constraints/            # catalogo vincoli configurabili (D1, D3, D6, T2, T3)
  Services/
    Solver/               # ProblemBuilder, SolverRunner, ResultImporter
    Validation/           # pre-validazione prima del solving
    Editor/               # spostamento/scambio lezioni nella griglia
    Export/               # export PDF
  Jobs/GenerateTimetable.php
resources/
  views/
  js/                     # moduli ES vanilla (nessun framework JS)
solver/
  solver.py               # entrypoint CP-SAT: JSON stdin -> JSON stdout
  constraints/            # un modulo per tipo di vincolo
  tests/
docs/
  analisi-orario-scuola-media.md
```

Per le convenzioni di codice, il contratto PHP↔solver e la roadmap delle fasi successive, vedi [`CLAUDE.md`](CLAUDE.md).
