# Orario Scuola Media

Applicativo web per generare e gestire l'orario settimanale di una scuola secondaria di I grado: anagrafiche, cattedre, vincoli configurabili, generazione automatica (OR-Tools CP-SAT), editor a griglia con drag&drop ed export PDF.

Cosa fa: anagrafiche (sedi, aule, discipline, quadri orari, docenti, classi, cattedre) con esporta/importa CSV, sostegno e didattica DADA, vincoli configurabili, generazione automatica asincrona con seed riproducibile, editor a griglia, export PDF (griglie e tabellone generale su un foglio), utenze con ruoli, dashboard operativa e **guida in-app** (pulsante *Aiuto* o tasto **F1**, in funzione del ruolo).

La specifica funzionale completa è in [`docs/analisi-orario-scuola-media.md`](docs/analisi-orario-scuola-media.md); le convenzioni di sviluppo sono in [`CLAUDE.md`](CLAUDE.md).

---

## Installazione semplice per Windows e Mac

Per chi non è uno sviluppatore: servono 3 passi, e il primo si fa una volta sola. Non occorre sapere cosa sia Docker o usare il terminale.

**Cosa serve**

- Un computer **Windows 10/11** (64 bit) oppure un **Mac** (Intel o Apple Silicon), con almeno 8 GB di memoria e circa 5 GB di spazio libero.
- Una connessione a internet, soltanto la prima volta (scarica i componenti).

### Passo 1 - Installare Docker Desktop (una volta sola)

Docker è il programma che fa funzionare Orario Scuola. È gratuito per scuole e istruzione.

1. Scarica la versione per il tuo computer da **<https://www.docker.com/products/docker-desktop/>**.
2. Installala e, se richiesto, riavvia il computer.
3. Apri "Docker Desktop" una volta, accetta le condizioni e aspetta che sia pronto (l'icona della balena smette di muoversi).

Se ti sembra complicato, chiedi aiuto a chi gestisce i computer della scuola: dopo questo passo il resto è semplicissimo.

### Passo 2 - Preparare la cartella

Ti serve la cartella del progetto, con dentro i file di avvio. Puoi averla in due modi:

- **Scaricarla da GitHub** (consigliato): vai su **<https://github.com/johnnybaida/orario-scuola>**, clicca il pulsante verde **Code** e poi **Download ZIP**. Il file scaricato è lo ZIP da estrarre qui sotto. I file di avvio sono nella cartella principale del progetto, in sei: `Avvia-`, `Ferma-` e `Aggiorna-Orario-Scuola`, ciascuno con il suffisso **`-Windows.bat`** (per Windows) o **`-Mac.command`** (per Mac). Usa solo quelli del tuo sistema.
- **Ricevere un file ZIP** da chi gestisce l'installazione: è lo stesso contenuto.

Nella cartella troverai pochi file. **Quelli che ti servono sono i quattro di avvio** (`Avvia-…` e `Ferma-…`, due per Windows e due per Mac) e questo `README.md`. Le cartelle `web`, `docs` e `docker` e gli altri file (`Dockerfile`, `compose.yaml`, `VERSION`, …) sono il programma stesso: **non vanno aperti né modificati**.

In entrambi i casi, **estrai lo ZIP** (su Windows: tasto destro → "Estrai tutto") in una cartella normale, ad esempio sul Desktop o in Documenti. Non avviare i file direttamente dentro lo ZIP.

Per **aggiornare** a una nuova versione basta fare doppio clic su `Aggiorna-Orario-Scuola-Windows.bat` (Windows) o `Aggiorna-Orario-Scuola-Mac.command` (Mac) (scarica da GitHub l'ultima versione e ricostruisce l'applicazione, senza bisogno di Git): i dati non si perdono. In alternativa riscarica lo ZIP a mano e ripeti questo passo e il successivo; chi ha clonato il progetto può usare `git pull`.

### Passo 3 - Avviare Orario Scuola

| Sistema | Fai doppio clic su | Se compare un avviso |
|---|---|---|
| **Windows** | `Avvia-Orario-Scuola-Windows.bat` | "Windows ha protetto il PC": clicca *Ulteriori informazioni* e poi *Esegui comunque*. |
| **Mac** | `Avvia-Orario-Scuola-Mac.command` | "Apple non è in grado di verificare…" (la finestra offre solo *Fine* / *Sposta nel Cestino*): è la protezione di macOS sui file scaricati. **Clicca *Fine* (non spostarlo nel Cestino)**, apri *Impostazioni di Sistema → Privacy e sicurezza*, scorri fino all'avviso sul file bloccato e premi **Apri comunque**; conferma con la password e rifai doppio clic. Va fatto **una volta per ognuno dei tre file** `.command` (Avvia, Ferma, Aggiorna). In alternativa, per sbloccarli tutti insieme: nell'app *Terminale* scrivi `xattr -dr com.apple.quarantine ` (con uno spazio), trascina la cartella del progetto e premi Invio. Con le versioni meno recenti di macOS basta tasto destro → *Apri* → *Apri*. Se dice "permesso negato": in *Terminale* scrivi `chmod +x ` (con uno spazio), trascina i tre file `.command`, premi Invio e riprova. |

Si apre una finestra nera con le istruzioni: controlla che Docker sia installato e acceso (lo accende se serve), avvia l'applicazione (`docker compose up -d --build`, vedi [Avvio con Docker](#avvio-con-docker)), attende che risponda e apre il browser su <http://localhost:8080>. **La prima volta ci vogliono alcuni minuti (anche 10): non chiuderla.**

**Primo accesso:** email `amministratore@scuola.test`, password `password`. Appena entri cambia la password dalla voce *Utenze* del menu. Nella scuola di esempio ci sono anche altri utenti di prova: sono elencati in [Accessi di prova](#accessi-di-prova-seed).

### Uso quotidiano

- Se Docker Desktop è aperto, Orario Scuola è già acceso: basta aprire il browser su <http://localhost:8080> (puoi salvarlo tra i preferiti). Se non si apre, rifai doppio clic su `Avvia-Orario-Scuola-Windows.bat` / `Avvia-Orario-Scuola-Mac.command`.
- Per **spegnerlo**: doppio clic su `Ferma-Orario-Scuola-Windows.bat` (Windows) o `Ferma-Orario-Scuola-Mac.command` (Mac). I dati (docenti, classi, orari, ...) **non** vengono cancellati. Chiudere la finestra nera non spegne l'applicazione.
- **Aggiornare** a una nuova versione: doppio clic su `Aggiorna-Orario-Scuola-Windows.bat` / `Aggiorna-Orario-Scuola-Mac.command` (oppure sostituisci la cartella con quella nuova e rifai doppio clic su `Avvia-…`). I dati restano: li conserva Docker, non la cartella.

### Usarlo da altri computer della scuola

Il computer dove è installato deve restare acceso con Docker aperto. Dagli altri computer collegati alla stessa rete si apre il browser su `http://INDIRIZZO-DEL-COMPUTER:8080` (l'indirizzo lo trova chi gestisce la rete). Se compare un avviso del firewall, consenti l'accesso alle reti private.

### Se qualcosa non va

- **Docker non parte o "non è acceso":** apri Docker Desktop a mano e aspetta che sia pronto. Su Windows serve la virtualizzazione attiva (se Docker lo segnala, chiedi assistenza).
- **Porta occupata, o il browser mostra un altro sito:** aggiungi alla cartella un file di testo chiamato `web/.env` con dentro la riga `DOCKER_APP_PORT=8081` e rilancia l'avvio (l'indirizzo diventa `http://localhost:8081`). Su Windows: Blocco note → *Salva con nome* → tipo *Tutti i file* → nome `web/.env`. Su Mac: nel *Terminale*, dalla cartella, scrivi `echo 'DOCKER_APP_PORT=8081' >> .env`. Se il file c'è già, aggiungi solo quella riga.
- **L'applicazione è lenta al primo avvio:** è normale, attendi.
- **Altri problemi:** copia o fotografa il testo della finestra nera e mandalo a chi gestisce l'installazione.

> **Attenzione:** `docker compose down -v` **cancella tutti i dati**. Non usarlo se non sei sicuro.

---

# Sezione per sviluppatori

## Avvio con Docker

Con Docker (e Docker Compose) si avvia tutto con un comando, senza installare PHP, Node, Python o il database:

```bash
docker compose up -d --build
```

Poi apri <http://localhost:8080> (la porta si cambia con `DOCKER_APP_PORT`). Al primo avvio il container: crea la chiave dell'applicazione, esegue le migrazioni, carica la **scuola di esempio** (≈15 classi, 40 docenti) e avvia il worker di coda. Accesso iniziale: `amministratore@scuola.test` / `password`: **cambia la password** (o impostala subito con `DOCKER_ADMIN_PASSWORD`, vedi sotto) prima di usarlo su un server raggiungibile da altri. Gli altri utenti di prova sono in [Accessi di prova](#accessi-di-prova-seed).

Servizi: `db` (MariaDB 11) e `app` (PHP + FrankenPHP con gli assets compilati e il solver Python). I dati stanno in due volumi, `dbdata` (database) e `storage` (chiave, log, file), e sopravvivono ai riavvii.

Per cambiare le impostazioni predefinite si possono aggiungere righe al file `web/.env` accanto a `compose.yaml` (o impostare variabili d'ambiente). **Chi non è uno sviluppatore non deve toccare nulla**: senza `web/.env` si usano i valori predefiniti. Queste variabili hanno il prefisso `DOCKER_` perché le legge solo Docker: il `web/.env` di Laravel dello sviluppo (`APP_URL`, `DB_PASSWORD`, `APP_KEY`, ...) **non** influisce sull'avvio con Docker, e viceversa.

| Variabile (facoltativa) | Significato | Predefinito |
|---|---|---|
| `DOCKER_APP_PORT` | porta sul computer | `8080` |
| `DOCKER_APP_URL` | indirizzo pubblico dell'applicazione | `http://localhost:8080` |
| `DOCKER_ADMIN_PASSWORD` | password dell'amministratore di esempio, impostata a ogni avvio | `password` |
| `DOCKER_SEED_ESEMPIO` | `1` = carica la scuola di esempio al primo avvio, `0` = non carica nulla | `1` |
| `DOCKER_CONTROLLO_AGGIORNAMENTI` | `false` = non controlla su GitHub se esiste una versione più recente | `true` |
| `DOCKER_DB_PASSWORD`, `DOCKER_DB_ROOT_PASSWORD` | password del database | `orario`, `root` |
| `DOCKER_APP_KEY` | chiave dell'applicazione (se vuota se ne genera una e si conserva nel volume) | vuota |

Comandi utili:

```bash
docker compose logs -f app      # log dell'applicazione
docker compose down             # ferma (i dati restano)
docker compose down -v          # ferma e CANCELLA database e file
docker compose up -d --build    # dopo un aggiornamento del codice
```

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

L'applicazione sta nella cartella **`web/`**: tutti i comandi qui sotto si lanciano da lì.

```bash
cd web

# dipendenze PHP e JS
composer install
npm install

# configurazione (il file .env di Laravel sta in web/, non nella radice del progetto)
cp .env.example .env
php artisan key:generate
```

Apri `web/.env` e imposta le credenziali del database (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) prima di continuare.

```bash
# schema + dati di esempio (15 classi, 40 docenti, quadro a 30 ore)
php artisan migrate --seed

# ambiente del solver Python (OR-Tools CP-SAT)
python3.11 -m venv solver/.venv
solver/.venv/bin/pip install -r solver/requirements.txt
```

> Il file `web/.env` nella **radice** del progetto serve solo a Docker (variabili `DOCKER_*`, vedi sopra) e non è quello di Laravel.

---

## Sviluppo

Servono **tre processi** in parallelo (tre terminali, o un multiplexer), tutti da `web/`:

```bash
cd web
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

## Versione e rilasci

La versione è nel file `VERSION` e compare in fondo alla barra laterale. L'amministratore vede un avviso quando il file `VERSION` del ramo `main` su GitHub ha un numero più alto di quello installato (nessun tag necessario; controllo in sola lettura, al massimo una volta all'ora; si spegne con `CONTROLLO_AGGIORNAMENTI=false`, su Docker `DOCKER_CONTROLLO_AGGIORNAMENTI=false`).

Per pubblicare una nuova versione:

```bash
echo 0.2.0 > VERSION && git commit -am "Versione 0.2.0"
git push                                        # basta questo: le installazioni vedono l'avviso entro un'ora
git tag v0.2.0 && git push --tags               # facoltativo: tag e release su GitHub con le note
```

---

## Test

```bash
cd web
php artisan test                    # test PHP (Feature + Unit)
solver/.venv/bin/pytest solver/tests   # test del solver Python
```

---

## Struttura del progetto

```
README.md, CLAUDE.md, AGENTS.md, VERSION, LICENSE
Avvia-/Ferma-/Aggiorna-Orario-Scuola-Windows.bat|-Mac.command   # launcher a doppio clic (installazione semplice); Aggiorna = scarica lo ZIP da GitHub e ricostruisce (senza Git)
Dockerfile, compose.yaml, docker/         # avvio con Docker
docs/
  analisi-orario-scuola-media.md   # specifica funzionale
  guida-utente.md                  # manuale mostrato nel pannello Aiuto
  design-system/                   # design system dell'interfaccia
web/                                # l'applicazione (Laravel): tutti i comandi di sviluppo si lanciano da qui
  app/
    Models/               # entità di dominio (nomi in italiano)
    Http/Controllers/
    Http/Requests/        # validazione dei form
    Support/              # ruoli/permessi, mappa pagina → sezione della guida
    Constraints/          # catalogo vincoli configurabili (D1, D3, D6, T2, T3)
    Services/
      Solver/             # ProblemBuilder, SolverRunner, ResultImporter
      Validation/         # pre-validazione prima del solving (con link per correggere)
      Editor/             # spostamento/scambio lezioni, controllo dell'orario
      Export/             # export PDF
      QueueWorker.php     # avvio/arresto del worker di coda dall'interfaccia
      SincronizzaRighe.php  # salvataggio delle righe ripetibili dei form
    Jobs/GenerateTimetable.php
  resources/
    views/                # Blade; components/ con i pezzi riusabili (toast, barre, righe ripetibili, ...)
    js/                   # moduli ES vanilla (nessun framework JS)
  solver/
    solver.py             # entrypoint CP-SAT: JSON stdin -> JSON stdout
    constraints/          # un modulo per tipo di vincolo
    tests/
  database/, routes/, config/, tests/, public/, storage/, composer.json, package.json, ...
```

Per le convenzioni di codice, l'interfaccia, i permessi, il contratto PHP↔solver e la roadmap delle fasi successive, vedi [`CLAUDE.md`](CLAUDE.md). Il manuale per gli utenti è in [`docs/guida-utente.md`](docs/guida-utente.md) (si aggiorna insieme alle funzioni).
