# 🗓️ Orario Scuola

**Genera e gestisce gli orari di una scuola**: inserisci docenti, classi e regole, e il programma propone l'orario completo, che poi puoi ritoccare a mano e stampare in PDF.

Funziona sul tuo computer, nel browser, **senza account**: internet serve solo per installarlo e per gli aggiornamenti.

> **Vuoi solo usarlo?** Vai a [Installazione su Windows e Mac](#-installazione-su-windows-e-mac): sono 3 passi, senza terminale.
> **Sei uno sviluppatore?** Vai a [Per sviluppatori](#-per-sviluppatori).

## Indice

- [Cosa fa](#-cosa-fa)
- [Installazione su Windows e Mac](#-installazione-su-windows-e-mac)
- [Uso quotidiano](#-uso-quotidiano)
- [Se qualcosa non va](#-se-qualcosa-non-va)
- [Per sviluppatori](#-per-sviluppatori)

---

## ✨ Cosa fa

| | |
|---|---|
| 🏫 **Più sedi** | Ogni sede ha docenti, discipline, quadri orari, scansione oraria, vincoli e orari propri. Si sceglie la sede dal menu in alto; una sede nuova può copiare la configurazione di un'altra. |
| 👩‍🏫 **Anagrafiche** | Sedi, aule, discipline, quadri orari, docenti, classi, cattedre, laboratori pomeridiani. Importa/esporta CSV di ogni lista e backup completo in ZIP. |
| 🧩 **Casi particolari** | Sostegno e compresenze, docenti CLIL, didattica DADA, mensa con assistenza alle pause, sospensioni dei docenti con passaggio delle cattedre ai supplenti. |
| 📐 **Vincoli configurabili** | Blocchi di ore consecutive, ore al giorno, fasce orarie, giorno libero, buche, spostamenti tra piani, ore attorno alla mensa, discipline del sostegno… |
| ⚙️ **Generazione automatica** | Il programma calcola l'orario rispettando i vincoli (motore OR-Tools), con risultato riproducibile; se non esiste una soluzione spiega quali regole sono in conflitto. |
| ✏️ **Modifica a mano** | Griglia con trascinamento, controllo dei conflitti, annulla/ripeti, stati dell'orario (bozza, revisione, approvato, pubblicato), versioni e duplicazione. |
| 🖨️ **PDF** | Fogli per classe, per docente e per aula, più il tabellone generale, pronti da stampare. |
| 👥 **Utenti e ruoli** | Amministratore, referente orario, segreteria, dirigente, docente… ognuno vede solo ciò che gli serve. Registro di tutte le modifiche. |
| 📖 **Guida integrata** | Pulsante **Aiuto** (o tasto **F1**) in ogni pagina, adatta al proprio ruolo. |

---

## 💻 Installazione su Windows e Mac

Servono **3 passi** e il primo si fa **una volta sola**. Non occorre sapere cosa sia Docker né usare il terminale.

**Cosa serve**

- Un computer **Windows 10/11** (64 bit) oppure un **Mac** (Intel o Apple Silicon)
- Almeno **8 GB di memoria** e circa **5 GB di spazio libero**
- Una connessione a internet, solo la prima volta (scarica i componenti)

### 1️⃣ Installa Docker Desktop *(una volta sola)*

Docker è il programma che fa funzionare Orario Scuola. È gratuito per scuole e istruzione.

1. Scarica la versione per il tuo computer da **<https://www.docker.com/products/docker-desktop/>**
2. Installalo e, se richiesto, riavvia il computer
3. Apri **Docker Desktop** una volta, accetta le condizioni e aspetta che sia pronto (l'icona della balena smette di muoversi)

> 💡 Se ti sembra complicato, chiedi aiuto a chi gestisce i computer della scuola: dopo questo passo il resto è semplicissimo.

### 2️⃣ Scarica la cartella del programma

1. Vai su **<https://github.com/johnnybaida/orario-scuola>**
2. Clicca il pulsante verde **Code**, poi **Download ZIP**
3. **Estrai lo ZIP** in una cartella normale, per esempio sul Desktop o in Documenti
   *(Windows: tasto destro → "Estrai tutto". Non avviare i file direttamente dentro lo ZIP.)*

In alternativa puoi ricevere lo stesso ZIP da chi gestisce l'installazione.

Nella cartella estratta **ti servono solo due file**, uno per sistema:

| Sistema | File di avvio |
|---|---|
| 🪟 Windows | `WIN-Orario-Scuola.bat` |
| 🍎 Mac | `MAC-Orario-Scuola.app` |

Tutto il resto (le cartelle `web`, `docs`, `docker` e file come `Dockerfile`, `compose.yaml`, `VERSION`) è il programma stesso: **non va aperto né modificato**.

### 3️⃣ Avvia Orario Scuola

Fai **doppio clic** sul file di avvio del tuo sistema. Si apre una finestra con **tre pulsanti**:

| Pulsante | Cosa fa |
|---|---|
| ▶️ **Avvia** | Accende Docker se serve, avvia il programma e apre il browser su <http://localhost:8080> |
| 🔄 **Aggiorna** | Scarica l'ultima versione da GitHub e la installa (i dati restano) |
| ⏹️ **Ferma** | Spegne il programma (i dati **non** vengono cancellati) |

Premi **Avvia**.

> ⏳ **La prima volta ci vogliono alcuni minuti (anche 10): non chiudere la finestra.** Le volte successive è questione di secondi.

Su Windows, nella stessa finestra, **Crea icona sul Desktop** aggiunge l'icona «Orario Scuola»: così non vedrai più il file `.bat`.

<details>
<summary><strong>⚠️ Compare un avviso di sicurezza all'apertura? Clicca qui</strong></summary>

È normale: il file viene da internet e il sistema chiede conferma. Si fa **una volta sola**.

**Windows** — «Windows ha protetto il PC»: clicca **Ulteriori informazioni**, poi **Esegui comunque**.

**Mac** — «Apple non è in grado di verificare…» (la finestra offre solo *Fine* / *Sposta nel Cestino*):

1. Clicca **Fine** (**non** spostare l'app nel Cestino)
2. Apri **Impostazioni di Sistema → Privacy e sicurezza**
3. Scorri fino all'avviso sull'app bloccata e premi **Apri comunque**
4. Conferma con la password e rifai doppio clic

Altri metodi, se il precedente non funziona:

- Nell'app **Terminale** scrivi `xattr -dr com.apple.quarantine ` (con uno spazio finale), trascina nella finestra la cartella del progetto e premi Invio.
- Con le versioni meno recenti di macOS basta tasto destro → **Apri** → **Apri**.
- Se dice «permesso negato»: nel **Terminale** scrivi `chmod +x ` (con uno spazio finale), trascina il file `MAC-Orario-Scuola.app/Contents/MacOS/orario-scuola`, premi Invio e riprova.
- Se macOS esegue l'app da una copia temporanea e non trova la cartella del progetto, ti chiede di sceglierla (una volta sola).

</details>

### 🔑 Primo accesso

| | |
|---|---|
| **Email** | `amministratore@scuola.test` |
| **Password** | `password` |

Appena entri, **cambia la password** dalla voce *Utenze* del menu del profilo (le tue iniziali, in alto a destra).

Il programma parte con una **scuola di esempio** (circa 15 classi e 40 docenti) per provarlo subito; ci sono anche altri utenti di prova, elencati in [Accessi di prova](#accessi-di-prova).

---

## 📅 Uso quotidiano

| Voglio… | Faccio così |
|---|---|
| **Usare il programma** | Se Docker Desktop è aperto è già acceso: apri il browser su <http://localhost:8080> (salvalo tra i preferiti). Se non si apre, apri il file di avvio e premi **Avvia**. |
| **Spegnerlo** | Apri il file di avvio e premi **Ferma**. I dati non si perdono. |
| **Fare un backup** | Da amministratore, pagina **Dati** → scarica lo ZIP con tutti i dati (e da lì si possono anche ricaricare). Fanne uno prima di ogni aggiornamento importante. |
| **Aggiornare** | Apri il file di avvio e premi **Aggiorna**. I dati restano: li conserva Docker, non la cartella. |
| **Usarlo da altri computer** | Vedi sotto. |

<details>
<summary><strong>🔄 Cosa fa esattamente «Aggiorna»</strong></summary>

Scarica da GitHub l'ultima versione e ricostruisce l'applicazione, senza bisogno di Git. La cartella viene **allineata** alla nuova versione: i file che non esistono più vengono **cancellati**, quindi non tenere nella cartella del programma file tuoi. Restano intatti solo i file `.env`, `.git` e, per chi sviluppa, `web/vendor`, `web/node_modules`, `web/storage` e `web/solver/.venv`.

In alternativa puoi riscaricare lo ZIP a mano e ripetere i passi 2 e 3. Chi ha clonato il progetto può usare `git pull`.

**Se arrivi da una versione precedente** (con i sei file `Avvia-…`, `Ferma-…`, `Aggiorna-…`): dopo il primo aggiornamento puoi cancellare a mano il vecchio `Aggiorna-Orario-Scuola-Windows.bat` o `Aggiorna-Orario-Scuola-Mac.command`, l'unico che resta.

</details>

### 🌐 Usarlo da altri computer della scuola

Il computer dove è installato deve restare **acceso, con Docker aperto**. Dagli altri computer della stessa rete si apre il browser su:

```
http://INDIRIZZO-DEL-COMPUTER:8080
```

L'indirizzo lo trova chi gestisce la rete. Se compare un avviso del firewall, consenti l'accesso alle **reti private**.

---

## 🆘 Se qualcosa non va

| Problema | Cosa fare |
|---|---|
| **Docker non parte o «non è acceso»** | Apri Docker Desktop a mano e aspetta che sia pronto. Su Windows serve la virtualizzazione attiva (se Docker lo segnala, chiedi assistenza). |
| **Il primo avvio sembra bloccato** | È normale, può richiedere fino a 10 minuti: attendi e non chiudere la finestra. |
| **Porta occupata, o il browser mostra un altro sito** | Cambia porta: vedi il riquadro qui sotto. |
| **Altro** | Copia o fotografa il testo che compare nella finestra (su Mac, nella finestra d'errore, **Mostra tutto**) e mandalo a chi gestisce l'installazione. |

<details>
<summary><strong>🔌 Come cambiare la porta (da 8080 a 8081)</strong></summary>

Aggiungi nella cartella del programma un file di testo chiamato `web/.env` con dentro questa riga:

```
DOCKER_APP_PORT=8081
```

Poi rilancia l'avvio: l'indirizzo diventa `http://localhost:8081`.

- **Windows:** Blocco note → *Salva con nome* → tipo *Tutti i file* → nome `web/.env`.
- **Mac:** nel **Terminale**, dalla cartella del programma, scrivi `echo 'DOCKER_APP_PORT=8081' >> .env`.
- Se il file esiste già, aggiungi soltanto quella riga.

</details>

> [!CAUTION]
> Il comando `docker compose down -v` **cancella tutti i dati**. Non usarlo se non sei sicuro: per spegnere il programma usa il pulsante **Ferma**.

---

# 🛠️ Per sviluppatori

Da qui in avanti il contenuto è per chi sviluppa o amministra l'installazione.

- [Avvio con Docker](#avvio-con-docker)
- [Requisiti e setup senza Docker](#requisiti-e-setup-senza-docker)
- [Sviluppo](#sviluppo)
- [Accessi di prova](#accessi-di-prova)
- [Test](#test)
- [Versione e rilasci](#versione-e-rilasci)
- [Struttura del progetto](#struttura-del-progetto)

Documentazione del progetto:

| Documento | Contenuto |
|---|---|
| [`docs/analisi-orario-scuola-media.md`](docs/analisi-orario-scuola-media.md) | Specifica funzionale completa (fonte di verità) |
| [`CLAUDE.md`](CLAUDE.md) | Convenzioni di sviluppo, interfaccia, permessi, contratto PHP↔solver, roadmap |
| [`docs/guida-utente.md`](docs/guida-utente.md) | Manuale per gli utenti, mostrato nel pannello *Aiuto* (si aggiorna insieme alle funzioni) |

**Stack:** Laravel 13 (PHP ≥ 8.3), MariaDB, Blade + JavaScript vanilla (Vite), solver Python 3 con OR-Tools CP-SAT, code su database, Docker Compose.

## Avvio con Docker

Con Docker e Docker Compose si avvia tutto con un comando, senza installare PHP, Node, Python o il database:

```bash
docker compose up -d --build
```

Poi apri <http://localhost:8080>. Al primo avvio il container crea la chiave dell'applicazione, esegue le migrazioni, carica la **scuola di esempio** (≈15 classi, 40 docenti) e avvia il worker di coda.

> [!WARNING]
> L'accesso iniziale è `amministratore@scuola.test` / `password`: **cambia la password** (o impostala subito con `DOCKER_ADMIN_PASSWORD`) prima di usarlo su un server raggiungibile da altri.

**Servizi:** `db` (MariaDB 11) e `app` (PHP + FrankenPHP con gli assets compilati e il solver Python). I dati stanno in due volumi, `dbdata` (database) e `storage` (chiave, log, file), e sopravvivono ai riavvii.

**Impostazioni.** Per cambiare i valori predefiniti aggiungi righe al file `web/.env` accanto a `compose.yaml` (o imposta variabili d'ambiente); senza quel file si usano i predefiniti. Queste variabili hanno il prefisso `DOCKER_` perché le legge solo Docker: il `web/.env` di Laravel dello sviluppo (`APP_URL`, `DB_PASSWORD`, `APP_KEY`, …) **non** influisce sull'avvio con Docker, e viceversa.

| Variabile (facoltativa) | Significato | Predefinito |
|---|---|---|
| `DOCKER_APP_PORT` | porta sul computer | `8080` |
| `DOCKER_APP_URL` | indirizzo pubblico dell'applicazione | `http://localhost:8080` |
| `DOCKER_ADMIN_PASSWORD` | password dell'amministratore di esempio, impostata a ogni avvio | `password` |
| `DOCKER_SEED_ESEMPIO` | `1` = carica la scuola di esempio al primo avvio, `0` = non carica nulla | `1` |
| `DOCKER_CONTROLLO_AGGIORNAMENTI` | `false` = non controlla su GitHub se esiste una versione più recente | `true` |
| `DOCKER_DB_PASSWORD`, `DOCKER_DB_ROOT_PASSWORD` | password del database | `orario`, `root` |
| `DOCKER_APP_KEY` | chiave dell'applicazione (se vuota se ne genera una e si conserva nel volume) | vuota |

**Comandi utili**

```bash
docker compose logs -f app      # log dell'applicazione
docker compose down             # ferma (i dati restano)
docker compose down -v          # ferma e CANCELLA database e file
docker compose up -d --build    # dopo un aggiornamento del codice
```

## Requisiti e setup senza Docker

In alternativa a tutto questo basta Docker (vedi sopra).

| Componente | Versione | Note |
|---|---|---|
| PHP | ≥ 8.3 | con estensioni `pdo_mysql`, `mbstring`, `xml`, `bcmath` (incluse in una installazione standard) |
| Composer | 2.x | |
| Node.js | ≥ 20 | per Vite/Tailwind |
| MariaDB / MySQL | 10.x / 8.x | un database vuoto, es. `orario_scuola` |
| Python | 3.11 | per il solver OR-Tools (CP-SAT) |

Non servono Apache o Nginx: `php artisan serve` basta per lo sviluppo. Se usi MAMP/MAMP PRO per il database, assicurati che il PHP da riga di comando (quello dei comandi qui sotto) abbia l'estensione `pdo_mysql`: non deve essere per forza il PHP incluso in MAMP.

L'applicazione sta nella cartella **`web/`**: tutti i comandi si lanciano da lì.

```bash
cd web

# dipendenze PHP e JS
composer install
npm install

# configurazione (il file .env di Laravel sta in web/, non nella radice)
cp .env.example .env
php artisan key:generate
```

Apri `web/.env` e imposta le credenziali del database (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`), poi:

```bash
# schema + dati di esempio (15 classi, 40 docenti, quadro a 30 ore)
php artisan migrate --seed

# ambiente del solver Python (OR-Tools CP-SAT)
python3.11 -m venv solver/.venv
solver/.venv/bin/pip install -r solver/requirements.txt
```

> Il file `.env` nella **radice** del progetto serve solo a Docker (variabili `DOCKER_*`) e non è quello di Laravel.

## Sviluppo

Servono **tre processi** in parallelo (tre terminali), tutti da `web/`:

```bash
cd web
php artisan serve        # applicazione: http://127.0.0.1:8000
npm run dev              # build Vite con hot reload di CSS/JS
php artisan queue:work   # worker di coda: necessario per generare l'orario
```

Senza worker attivo una generazione resta «in coda» a tempo indeterminato. Il worker si può anche **avviare e fermare dall'interfaccia** (pagina *Genera orario* e dashboard; l'arresto è sicuro, termina il job in corso) e **parte da solo** premendo *Avvia generazione*: in sviluppo il terzo terminale è quindi facoltativo.

### Accessi di prova

Password per tutti: `password`.

| Email | Ruolo |
|---|---|
| `amministratore@scuola.test` | Amministratore |
| `referente_orario@scuola.test` | Referente Orario (anagrafiche, vincoli, generazione) |
| `referente_sostituzioni@scuola.test` | Referente Sostituzioni |
| `segreteria@scuola.test` | Segreteria (docenti e classi) |
| `docente@scuola.test` | Docente |
| `ds@scuola.test` | Dirigente Scolastico |

## Test

```bash
cd web
php artisan test                       # test PHP (Feature + Unit)
solver/.venv/bin/pytest solver/tests   # test del solver Python
```

Se il venv è stato spostato di cartella usa `solver/.venv/bin/python3 -m pytest solver/tests`. I test PHP usano dati fissi (seme di Faker bloccato) e non richiedono gli asset compilati.

## Versione e rilasci

La versione è nel file `VERSION` e compare in fondo alla barra laterale. L'amministratore vede un avviso quando il file `VERSION` del ramo `main` su GitHub ha un numero più alto di quello installato (nessun tag necessario; controllo in sola lettura, senza cache, all'apertura della dashboard, quindi a ogni accesso). Si spegne con `CONTROLLO_AGGIORNAMENTI=false` (su Docker `DOCKER_CONTROLLO_AGGIORNAMENTI=false`).

Per pubblicare una nuova versione:

```bash
echo 0.2.0 > VERSION && git commit -am "Versione 0.2.0"
git push                              # basta questo: le installazioni vedono l'avviso al prossimo accesso
git tag v0.2.0 && git push --tags     # facoltativo: tag e release su GitHub con le note
```

## Struttura del progetto

```
README.md, CLAUDE.md, AGENTS.md, VERSION, LICENSE
WIN-Orario-Scuola.bat, MAC-Orario-Scuola.app, risorse/   # avviatori a doppio clic (Avvia, Aggiorna, Ferma); risorse/ = icone
Dockerfile, compose.yaml, docker/         # avvio con Docker
docs/
  analisi-orario-scuola-media.md   # specifica funzionale
  guida-utente.md                  # manuale mostrato nel pannello Aiuto
  design-system/                   # design system dell'interfaccia
web/                                # l'applicazione (Laravel): i comandi di sviluppo si lanciano da qui
  app/
    Models/               # entità di dominio (nomi in italiano)
      Concerns/           # Auditable (registro attività), PerSede/PerSedeVia (dati per sede)
    Http/Controllers/
    Http/Requests/        # validazione dei form
    Support/              # ruoli/permessi, mappa pagina → sezione della guida
    Constraints/          # catalogo dei vincoli configurabili
    Services/
      Solver/             # ProblemBuilder, SolverRunner, ResultImporter
      Validation/         # pre-validazione prima del solving (con link per correggere)
      Editor/             # spostamento/scambio lezioni, controllo dell'orario
      Export/             # export PDF
      Substitution/       # passaggio delle cattedre ai supplenti
      SedeCorrente.php, CopiaDaSede.php   # sede in cui si lavora, copia della configurazione tra sedi
      DatiScuola.php, ListeCsv.php        # backup ZIP e import/export CSV
      QueueWorker.php     # avvio/arresto del worker di coda dall'interfaccia
    Jobs/GenerateTimetable.php
  resources/
    views/                # Blade; components/ con i pezzi riusabili
    js/                   # moduli ES vanilla (nessun framework JS)
  solver/
    solver.py             # entrypoint CP-SAT: JSON stdin -> JSON stdout
    constraints/          # un modulo per tipo di vincolo
    tests/
  database/, routes/, config/, tests/, public/, storage/, composer.json, package.json, …
```

## Crediti

L'icona degli avviatori (`risorse/`) è di Flaticon (<https://www.flaticon.com/free-icon/calendar_2490641>): la licenza gratuita richiede di citare l'autore, indicato nella pagina dell'icona.
