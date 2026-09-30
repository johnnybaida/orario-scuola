# Analisi funzionale — Applicativo web per l'orario della Scuola Secondaria di I grado

> Versione 1.2 — documento di analisi, non di progettazione tecnica di dettaglio.
> Tutte le decisioni sono riepilogate al §17.

---

## 1. Obiettivo

Realizzare un'applicazione web che permetta a una scuola media di:

1. censire classi, docenti, discipline, aule e quadri orari;
2. assegnare le cattedre (docente ↔ classe ↔ disciplina), manualmente o con proposta automatica;
3. **generare automaticamente l'orario settimanale** con componente casuale controllata (seed), rispettando ore per disciplina e vincoli;
4. definire **vincoli configurabili** (rigidi e preferenziali) a livello globale, classe, docente, disciplina, aula;
5. gestire **assenze docenti e sostituzioni giornaliere**;
6. gestire **modifiche manuali**, versioni e pubblicazione dell'orario;
7. esportare/stampare le viste (per classe, docente, aula, generale) in PDF ed Excel.

Fuori perimetro: registro elettronico, valutazioni, anagrafica e presenze alunni, stipendi e pagamento ore eccedenti (solo contatore), educatori e assistenti (OSA/ASACOM). Una sola scuola per installazione.

---

## 2. Contesto normativo di riferimento

| Tema | Riferimento | Impatto sul sistema |
|---|---|---|
| Quadri orari | DPR 89/2009 | Monte ore settimanale per disciplina, tempo normale (30h) e prolungato (36h, estendibile a 40) |
| Obbligo di servizio docenti | CCNL comparto Istruzione | Cattedra intera = 18 ore frontali settimanali; ore residue "a disposizione" |
| Organico dell'autonomia | L. 107/2015 | Ore di potenziamento utilizzabili per progetti e sostituzioni |
| Educazione civica | L. 92/2019 e Linee guida 2024 | 33 ore annue trasversali, **non** è una disciplina a sé nell'orario (opzionalmente tracciabile) |
| Indirizzo musicale | DM 176/2022 | Ore di strumento per gruppi/singoli, spesso pomeridiane |
| Sostegno | L. 104/1992, D.Lgs. 66/2017 | Ore di sostegno per alunno/classe, docente contitolare |
| IRC e alternativa | Concordato / normativa IRC | 1h/settimana; alunni non avvalentisi → attività alternativa o uscita |
| Nuove Indicazioni Nazionali | DM 221/2025 | Dal 2026/27 si applicano gradualmente dalle classi prime; **Latino per l'educazione linguistica (LEL)** opzionale, 1 h settimanale, nelle classi seconde e terze |

**Nota LEL**: in attesa di modifica del DPR 89/2009, le scuole possono attivarlo tramite approfondimento di lettere, orario extracurricolare o gruppi anche di classi diverse. Il sistema deve quindi supportare **discipline opzionali per gruppi di alunni trasversali alle classi** (vedi §5.6).

---

## 3. Glossario

| Termine | Definizione |
|---|---|
| **Slot** | Unità oraria in una specifica giornata (es. Lun-3ª ora) |
| **Unità oraria** | Durata della lezione (60', 55', 50'…); se < 60' si generano minuti da recuperare |
| **Lezione** | Istanza da collocare: (classe/gruppo, disciplina, docente/i, durata in slot) |
| **Cattedra** | Insieme delle ore assegnate a un docente (intera 18h o spezzone) |
| **COE** | Cattedra Orario Esterna: docente con ore su più scuole |
| **Ora buca / finestra** | Ora libera del docente tra due lezioni nella stessa giornata |
| **Ora a disposizione** | Ora di servizio senza lezione, usata per sostituzioni |
| **Compresenza** | Due docenti nella stessa classe nello stesso slot |
| **Giorno libero** | Giorno senza servizio (prassi, non diritto contrattuale) |
| **Vincolo rigido (hard)** | Se violato, l'orario non è valido |
| **Vincolo preferenziale (soft)** | Violabile, ma con penalità pesata |

---

## 4. Attori e ruoli

| Ruolo | Permessi principali |
|---|---|
| **Amministratore di sistema** | Configurazione tecnica, utenti, backup |
| **Dirigente Scolastico (DS)** | Approva e pubblica l'orario, vede tutto |
| **Referente orario** (collaboratore DS) | Anagrafiche, vincoli, generazione, modifiche manuali |
| **Referente sostituzioni** (vicepreside/fiduciario di plesso) | Gestione assenze e sostituzioni giornaliere |
| **Segreteria** | Inserimento assenze da istanze/permessi, anagrafiche docenti |
| **Docente** | Consultazione proprio orario, proprie sostituzioni, invio desiderata |
| **Pubblico / famiglie** (opzionale) | Consultazione orario classi pubblicato |

**Multi-plesso**: una scuola (o Istituto Comprensivo) può avere più plessi/sedi. Il sistema deve modellare le sedi e i tempi di spostamento tra esse.

---

## 5. Dominio: entità e regole

### 5.1 Anno scolastico e calendario

- **Anno scolastico**: date inizio/fine, festività, sospensioni.
- **Periodi di validità dell'orario**: orario provvisorio (inizio anno, ridotto), orario definitivo, variazioni in corso d'anno. Ogni orario ha data di inizio/fine.
- **Settimana**: 5 giorni (settimana corta) o 6 giorni, configurabile per plesso o per classe.
- **Scansione oraria di istituto**: definita una sola volta a livello di scuola e **ereditata da tutte le classi**. Per ogni giorno: numero di ore, orario di inizio, **durata unica dell'ora** per tutta la scuola (configurabile, default 50'), **numero e posizione degli intervalli**, eventuali ore pomeridiane e mensa.
    - Le classi a tempo prolungato usano anche gli slot pomeridiani della stessa griglia; le altre ne usano un sottoinsieme.
    - Gli intervalli non sono slot. Di default un blocco di due ore **può stare a cavallo dell'intervallo**; il vincolo D7 permette di vietarlo per singole discipline.
- **Recupero minuti** (unità oraria < 60'): solo calcolo e report del debito orario di docenti e classi; nessuna pianificazione del recupero.

### 5.2 Sedi, aule e risorse

| Entità | Attributi |
|---|---|
| **Sede/plesso** | nome, indirizzo, tempi di spostamento verso altre sedi (minuti) |
| **Aula** | sede, tipo (classe, laboratorio, palestra, aula musica, aula sostegno, aula alternativa IRC), capienza, accessibilità |
| **Risorsa condivisa** | es. LIM mobile, laboratorio informatica — con capacità (n. classi contemporanee) |

Regole:
- Ogni classe ha di norma un'**aula base**.
- Alcune discipline richiedono un tipo aula (es. Scienze motorie → palestra; Tecnologia → laboratorio se previsto).
- La palestra può ospitare **più classi contemporaneamente** (capacità configurabile).

### 5.3 Discipline e quadri orari

**Disciplina**: codice, nome, classe di concorso associata (es. A022 Lettere, A028 Matematica e scienze, AB25 Inglese, AA25/AC25 seconda lingua, A001 Arte, A030 Musica, A049 Scienze motorie, A060 Tecnologia, IRC, Sostegno ADSS, strumento musicale AJ56/…).

**Quadro orario** (template assegnabile a classi) — esempio tempo normale 30h:

| Disciplina | Ore/sett. | Note |
|---|---|---|
| Italiano, Storia, Geografia | 9 + 1 approfondimento | La scuola decide la ripartizione (es. Ita 6, Sto 2, Geo 2) |
| Matematica e Scienze | 6 | Spesso ripartite Mat 4 + Sci 2 (stesso docente) |
| Tecnologia | 2 | |
| Inglese | 3 | 5 in caso di inglese potenziato |
| Seconda lingua comunitaria | 2 | Assente con inglese potenziato; può essere articolata per gruppi |
| Arte e immagine | 2 | |
| Musica | 2 | |
| Scienze motorie e sportive | 2 | |
| Religione cattolica / Alternativa | 1 | |
| **Totale** | **30** | |

Varianti da supportare come template distinti:
- **Tempo prolungato** (36h, fino a 40h): ore aggiuntive di lettere/matematica + **mensa** (slot di mensa con assistenza docente, conteggiata nel servizio).
- **Inglese potenziato**.
- **Indirizzo musicale**: ore di strumento/teoria/musica d'insieme per gruppi, anche pomeridiane.
- **LEL (Latino)**: +1h opzionale per classi 2ª e 3ª dal 2026/27.
- **Sperimentazioni/autonomia**: il quadro è completamente personalizzabile (quote di autonomia del curricolo).

Le **sotto-discipline** (Italiano/Storia/Geografia, Matematica/Scienze) vanno modellate come discipline distinte con stesso docente, così i vincoli possono riferirsi a ciascuna (es. "Italiano almeno 2 ore consecutive").

### 5.4 Classi e gruppi

| Entità | Attributi |
|---|---|
| **Classe** | anno (1ª/2ª/3ª), sezione, sede, aula base, quadro orario, tempo scuola, **n. totale alunni**, coordinatore |
| **Gruppo** | lezione separata seguita da parte della classe o da più classi (seconda lingua articolata, alternativa IRC, LEL, strumento, recupero/potenziamento): classi coinvolte, disciplina, docente, **n. partecipanti** (serve solo per la capienza aula) |

Gli alunni **non sono censiti**: si usano solo contatori per classe e per gruppo. Per il sostegno vedi §5.6.

Regola chiave: quando una lezione riguarda un **gruppo interclasse**, tutte le classi di provenienza devono avere in quello slot un'attività compatibile (es. classi 2A e 2B: francese e spagnolo in parallelo nello stesso slot; IRC e alternativa in parallelo).

### 5.5 Docenti

| Attributo | Descrizione |
|---|---|
| Anagrafica | nome, email, classi di concorso abilitanti |
| **Tipo contratto** | tempo indeterminato / determinato (annuale, fino al termine attività, supplenza breve) |
| **Tipo cattedra** | intera (18h), spezzone, COE (con quota ore per ciascuna scuola) |
| **Regime** | tempo pieno / **part-time** (orizzontale = ogni giorno ridotto, verticale = meno giorni, misto) con ore settimanali e giorni lavorativi |
| **Posto** | comune, **sostegno**, potenziamento, IRC, strumento musicale |
| Ore frontali | ore di lezione dovute |
| Ore a disposizione | differenza tra ore dovute e ore di lezione assegnate (es. 18 − 16 = 2) |
| Sedi | sedi di servizio (per COE e multi-plesso) |
| **Indisponibilità** | slot/giorni in cui il docente non può essere collocato (servizio in altra scuola, part-time verticale, permessi L.104, ecc.) |
| **Desiderata** | preferenze (giorno libero richiesto, prime/ultime ore da evitare…) con priorità |
| Ruoli | coordinatore, collaboratore DS, referente (possono dare esoneri/riduzioni) |

Regole:
- Somma ore di lezione assegnate ≤ ore contrattuali; lo scarto diventa ore a disposizione.
- **Docente COE**: il sistema gestisce solo le sue **indisponibilità** (giorni/ore nell'altra scuola) inserite dal referente; nessun coordinamento con l'orario dell'altra scuola.
- **Docente IRC**: insegna in molte classi (1h ciascuna) → è tipicamente il docente più vincolato; va collocato presto nell'algoritmo.

### 5.6 Sostegno

Il docente di sostegno è **contitolare della classe**: non insegna una disciplina propria e le sue ore sono **compresenze** con le lezioni curricolari (non occupano uno slot "di classe").

Modello senza anagrafica alunni:

- Per ogni classe: **n. alunni con sostegno** e, per ciascuno (identificato solo da un codice anonimo, es. "1B-S1"), le **ore settimanali di sostegno**.
- Le ore di un alunno possono essere coperte da **più docenti di sostegno**: il sistema le ripartisce tra i docenti assegnati alla classe, salvo vincolo specifico (S4 "docente unico").
- Il referente assegna i docenti di sostegno alla classe con un monte ore (es. Rossi 9h in 1B); la somma deve coprire le ore richieste dagli alunni della classe.
- Con più alunni con sostegno nella stessa classe, il **conteggio delle ore è configurabile**: impostazione di istituto, sovrascrivibile per singola classe.
    - **Per alunno**: ogni ora di un docente in compresenza vale per un solo alunno.
    - **Per classe**: un docente in compresenza segue contemporaneamente più alunni della classe e l'ora vale per ciascuno.
- Vincoli tipici (vedi §8.2, S1–S4): discipline da privilegiare (es. Italiano e Matematica), discipline escluse, distribuzione su più giorni, docente unico.
- Un docente con ore in più classi non può trovarsi in due classi nello stesso slot (H2).

Educatori e assistenti (OSA/ASACOM) sono fuori perimetro.

### 5.7 IRC e attività alternativa

- 1h/settimana per classe.
- Alunni non avvalentisi: attività alternativa (docente dedicato, in aula alternativa), studio individuale assistito, uscita/entrata posticipata.
- Se si sceglie l'uscita/entrata, l'ora di IRC va collocata **alla prima o all'ultima ora** → vincolo configurabile per classe.
- Attività alternativa per gruppi interclasse → stessa logica dei gruppi (§5.4).

---

## 6. Cattedre: assegnazione docente ↔ classe ↔ disciplina

Due modalità:

1. **Manuale** (prassi reale: il DS assegna le cattedre considerando continuità didattica e criteri del Collegio).
2. **Proposta automatica** (randomica con vincoli), da rivedere manualmente.

Vincoli dell'assegnazione:
- il docente deve possedere la classe di concorso della disciplina;
- ore assegnate ≤ ore contrattuali (con eventuale tolleranza a 0 per ore eccedenti);
- **continuità didattica** (preferenziale): stesso docente sulla stessa sezione negli anni;
- cattedra "per sezione" (es. Lettere 1A-2A-3A) come preferenza configurabile;
- Lettere: un docente tipicamente copre Ita+Sto+Geo in una classe (10h) + approfondimento in un'altra = 18h; il sistema deve supportare la **composizione di cattedre** su più classi;
- stesso docente per sotto-discipline accorpate (Mat+Sci), vincolo configurabile.

Output: **matrice cattedre** (righe docenti, colonne classi, celle = disciplina/ore) con evidenza di ore scoperte e docenti sotto/sovra-utilizzati.

---

## 7. Generazione dell'orario

### 7.1 Formulazione del problema

Problema di **scheduling vincolato** (NP-difficile). Input:
- insieme delle **lezioni da collocare**, derivate dalle cattedre: per ogni (classe/gruppo, disciplina) → N ore, eventualmente già suddivise in blocchi (es. Italiano 6h = 2+2+1+1);
- insieme degli slot disponibili;
- docenti, aule, vincoli.

Output: assegnazione di ogni lezione a (giorno, slot iniziale, aula).

### 7.2 Vincoli rigidi di sistema (sempre attivi, non disattivabili)

| # | Vincolo |
|---|---|
| H1 | Una classe ha al massimo una lezione per slot (eccetto compresenze e gruppi paralleli dichiarati) |
| H2 | Un docente è in al massimo un luogo per slot |
| H3 | Un'aula non supera la sua capacità per slot |
| H4 | Ogni classe ha esattamente il numero di ore previsto per ciascuna disciplina |
| H5 | Ogni classe ha tutti gli slot del proprio tempo scuola coperti (nessuna uscita anticipata non prevista) |
| H6 | Nessuna lezione in slot di indisponibilità del docente |
| H7 | Rispetto del tipo di aula richiesto dalla disciplina |
| H8 | Rispetto del tempo di spostamento tra sedi |
| H9 | Gruppi interclasse: tutte le classi coinvolte sono libere/compatibili nello stesso slot |
| H10 | Lezioni fissate manualmente ("bloccate") non vengono spostate |

### 7.3 Vincoli configurabili (modalità vincoli)

Vedi §8. Ogni vincolo configurabile può essere impostato come **rigido** o **preferenziale con peso**.

### 7.4 Funzione obiettivo (qualità dell'orario)

Minimizzare la somma pesata delle violazioni dei vincoli soft. Metriche mostrate all'utente:
- n. ore buche totali e per docente;
- n. giorni liberi concessi / richiesti;
- distribuzione discipline nella settimana (es. stessa disciplina non tutta concentrata);
- n. ultime ore per disciplina "pesante";
- n. desiderata rispettati;
- **punteggio complessivo** e dettaglio violazioni.

### 7.5 Componente randomica

- Ogni generazione usa un **seed** registrato → risultati riproducibili.
- "Rigenera" produce una soluzione diversa con nuovo seed.
- Possibilità di generare **N varianti** e confrontarle per punteggio.
- Casualità usata per: ordine delle lezioni a parità di priorità, scelta tra slot equivalenti, perturbazioni nella fase di ottimizzazione.

### 7.6 Strategia algoritmica

| Opzione | Pro | Contro |
|---|---|---|
| **A. Solver CP-SAT (Google OR-Tools)** | Standard de facto per timetabling, gestisce hard/soft nativamente, seed e time limit, soluzioni di alta qualità | Richiede Python sul server (script invocato dal backend PHP) |
| **B. Euristica custom** (costruttiva + ricerca locale: tabu search / simulated annealing) | Controllo totale, può girare nello stack esistente | Sviluppo e tuning lunghi, qualità inferiore sui casi difficili |
| **C. Ibrida** | Costruttiva veloce per anteprima + solver per ottimizzazione | Più complessa |

**Scelta: A, OR-Tools CP-SAT in locale.** OR-Tools è una libreria open source di Google (licenza Apache 2.0): si installa con `pip install ortools` e gira interamente sul server, senza servizi cloud né connessione a internet.

Integrazione con Laravel: un job in coda lancia uno script Python (Symfony Process), gli passa il problema in JSON e legge la soluzione in JSON. Non serve un microservizio separato.

Requisiti comuni a ogni opzione:
- esecuzione **asincrona** (job in coda) con avanzamento, time limit configurabile e annullamento;
- **generazione parziale**: rigenerare solo alcune classi/giorni mantenendo il resto bloccato;
- **diagnostica di infattibilità**: se non esiste soluzione, indicare i vincoli in conflitto (es. "docente Rossi: 20h assegnate ma solo 18 slot disponibili"; "IRC: 15 classi in 12 slot disponibili del docente");
- **pre-validazione** prima della generazione (controlli di capacità, vedi §7.7).

### 7.7 Pre-validazione (prima del solving)

- ore quadro orario = ore coperte da cattedre per ogni classe;
- ore docente ≤ slot disponibili del docente;
- ore per tipo aula ≤ capacità aule per slot (es. 2h × 15 classi di motoria = 30 ≤ slot palestra × capacità);
- vincoli contraddittori (es. "Italiano blocchi da 2" con 5 ore → impossibile senza un blocco da 1: segnalare o richiedere pattern esplicito);
- gruppi interclasse con classi a quadri orari incompatibili.

---

## 8. Modalità vincoli

### 8.1 Principi

- Catalogo di **tipi di vincolo parametrici** (non linguaggio libero) → UI guidata, validazione semplice, traduzione affidabile verso il solver.
- Ogni vincolo ha: `tipo`, `ambito` (globale / classe / gruppo di classi / docente / disciplina / aula), `parametri`, `severità` (rigido | preferenziale), `peso` (1–100), `attivo`, `nota`.
- **Ereditarietà**: vincolo globale → sovrascrivibile a livello classe/docente (il più specifico vince).
- **Profili di vincoli** salvabili e riutilizzabili tra anni scolastici.

### 8.2 Catalogo vincoli (v1)

**Distribuzione delle discipline (ambito: classe o disciplina)**

| Codice | Vincolo | Parametri | Esempio |
|---|---|---|---|
| D1 | Blocco consecutivo minimo | disciplina, min ore consecutive, n. blocchi | Italiano: almeno 2 ore consecutive (1 volta/sett.) |
| D2 | Pattern di suddivisione | disciplina, pattern | Italiano 6h = 2+2+1+1 |
| D3 | Max ore/giorno per disciplina | disciplina, max | Matematica max 2h/giorno |
| D4 | Min/max giorni con la disciplina | disciplina, min, max | Inglese su 3 giorni distinti |
| D5 | Giorni non consecutivi | disciplina | Scienze motorie non in giorni consecutivi |
| D6 | Fascia oraria vietata/preferita | disciplina, slot | Motoria non alla 1ª ora; IRC alla 1ª o ultima |
| D7 | Blocco non a cavallo dell'intervallo | disciplina | Arte 2h senza intervallo in mezzo |
| D8 | Sequenza vietata/preferita | disciplina A, disciplina B | Non Motoria subito prima di Matematica |
| D9 | Max discipline "pesanti" al giorno | elenco discipline, max | Max 2 tra Mat/Ita/Ing nelle ultime ore |
| D10 | Stessa disciplina non ultima ora più di N volte | disciplina, N | |
| D11 | Parallelo tra classi | disciplina, classi | 2ª lingua in parallelo 3A-3B (gruppi) |

**Docente (ambito: docente o globale)**

| Codice | Vincolo | Parametri | Esempio |
|---|---|---|---|
| T1 | Indisponibilità | giorni/slot | Prof. Bianchi non il mercoledì |
| T2 | Giorno libero | n. giorni, giorni preferiti (ordinati) | 1 giorno libero, preferibilmente sabato |
| T3 | Max ore buche | per giorno / per settimana | Max 1 buca/giorno, 3/settimana |
| T4 | Min/max ore di lezione al giorno | min, max | Min 2 (evitare giornate da 1h), max 5 |
| T5 | Max ore consecutive | max | Max 4 ore consecutive |
| T6 | Entrata/uscita | non prima di / non oltre slot | Non prima della 2ª ora il lunedì |
| T7 | Giorni specifici di servizio (part-time verticale) | giorni | Solo lun-mar-gio |
| T8 | Ora di ricevimento | n. ore, fascia | 1h ricevimento in orario mattutino (non didattica) |
| T9 | Ore a disposizione distribuite | min per giorno | Almeno 1 ora a disposizione ogni giorno di servizio |
| T10 | Spostamento sede | min slot liberi tra sedi | 1 slot tra plesso A e plesso B |

**Classe (ambito: classe)**

| Codice | Vincolo | Esempio |
|---|---|---|
| C1 | Orario di ingresso/uscita differenziato | Classi prime escono alla 5ª il sabato |
| C2 | Rientri pomeridiani | Tempo prolungato: rientro mar e gio |
| C3 | Mensa | Slot mensa fisso con docente di assistenza |
| C4 | Stesso docente non più di N ore/giorno in classe | Lettere max 3h/giorno |

**Sostegno**

| Codice | Vincolo | Esempio |
|---|---|---|
| S1 | Discipline preferite | Sostegno in 1B soprattutto su Ita/Mat |
| S2 | Discipline escluse | Non in Motoria |
| S3 | Distribuzione minima | Su almeno 4 giorni |
| S4 | Docente unico per alunno | Le ore di 1B-S1 tutte a un solo docente |

**Aule/risorse**

| Codice | Vincolo | Esempio |
|---|---|---|
| R1 | Capacità contemporanea | Palestra max 2 classi |
| R2 | Indisponibilità aula | Laboratorio occupato mar 3-4 |
| R3 | Aula fissa per disciplina/classe | 3A usa sempre lab. tecnologia per Tecnologia |

**Fissaggi manuali**

| Codice | Vincolo |
|---|---|
| F1 | Lezione fissata in uno slot (bloccata) |
| F2 | Slot vietato per una classe |

### 8.3 Rappresentazione (esempio JSON)

```json
{
  "id": "vin_0142",
  "tipo": "D1_BLOCCO_MIN_CONSECUTIVO",
  "ambito": { "livello": "classe", "ids": ["1A", "1B"] },
  "parametri": { "disciplina": "ITA", "min_consecutive": 2, "n_blocchi_min": 1 },
  "severita": "rigido",
  "peso": null,
  "attivo": true,
  "nota": "Delibera collegio 12/09"
}
```

```json
{
  "id": "vin_0203",
  "tipo": "T2_GIORNO_LIBERO",
  "ambito": { "livello": "docente", "ids": ["doc_rossi"] },
  "parametri": { "n_giorni": 1, "preferenze": ["SAB", "LUN"] },
  "severita": "preferenziale",
  "peso": 60,
  "attivo": true
}
```

### 8.4 UI vincoli

- Elenco filtrabile per ambito/tipo/severità.
- Wizard di creazione per tipo con anteprima in linguaggio naturale ("In 1A e 1B, Italiano deve avere almeno un blocco di 2 ore consecutive — vincolo rigido").
- Indicatore di impatto: dopo la generazione, per ogni vincolo soft → rispettato / violato (n. volte).
- **Rilassamento guidato** in caso di infattibilità: il sistema propone quali vincoli rendere soft.

---

## 9. Modifica manuale e ciclo di vita dell'orario

### 9.1 Editor

- Griglia drag&drop per **vista classe** e **vista docente**.
- Durante il trascinamento: evidenza slot validi/non validi e vincoli che verrebbero violati.
- **Scambio** di due lezioni (anche tra classi con docente comune).
- **Blocco** di lezioni/giorni/classi prima di rigenerare il resto.
- Undo/redo, storico modifiche con autore.

### 9.2 Stati

`Bozza` → `In revisione` → `Approvato (DS)` → `Pubblicato` → `Archiviato`

- Più **versioni** per anno scolastico; una sola pubblicata per periodo di validità.
- **Confronto tra versioni** (diff per classe/docente) per comunicare le variazioni.

---

## 10. Assenze e sostituzioni

### 10.1 Assenze

| Attributo | Descrizione |
|---|---|
| Docente | |
| Tipo | malattia, permesso retribuito, permesso breve (da recuperare), ferie, L.104, formazione, uscita didattica/accompagnatore, sciopero, altro |
| Periodo | intera giornata / più giorni / singoli slot |
| Nomina supplente | se l'assenza è lunga, collegamento a supplente temporaneo (nuovo docente con validità limitata che eredita l'orario) |

Anche le **classi** possono essere "assenti" (uscita didattica, viaggio d'istruzione): i docenti di quelle classi diventano **liberi** e quindi disponibili per sostituzioni; i docenti accompagnatori risultano assenti per le altre classi.

**Permessi brevi**: le ore fruite generano un **debito** da recuperare; il sistema tiene il saldo e propone il docente in debito come sostituto prioritario.

### 10.2 Ordine di priorità per la scelta del sostituto (configurabile)

Ordine adottato di default, modificabile dalla scuola in configurazione:

1. docente in **compresenza** già presente nella classe (non sostegno);
2. docente con **permesso breve da recuperare**;
3. docente con **ora a disposizione** / di potenziamento in quello slot;
4. docente della **stessa classe** libero in quello slot;
5. docente della **stessa disciplina** libero;
6. docente disponibile a **ore eccedenti** (a pagamento) — con contatore e tetto;
7. docente di sostegno, solo secondo regolamento d'istituto e su scelta del referente (l'assenza degli alunni non è registrata);
8. **divisione della classe** su altre classi (ultima risorsa, con elenco classi di destinazione).

Criteri di equità: contatore sostituzioni per docente (mensile/annuale), rotazione.

### 10.3 Funzionalità

- **Proposta automatica** delle sostituzioni del giorno con motivazione (livello di priorità usato).
- Conferma/modifica manuale.
- Vincoli rispettati anche in sostituzione: tempo di spostamento tra sedi, non superare max ore/giorno configurabile.
- **Foglio sostituzioni giornaliero** stampabile/PDF e consultabile dai docenti nell'applicazione (nessun sistema di notifica).
- Uscite anticipate/entrate posticipate della classe (se previsto dal regolamento e comunicato alle famiglie) come esito possibile.
- Report: ore eccedenti per docente (per la segreteria), recuperi effettuati, classi scoperte.

---

## 11. Viste ed export

| Vista | Contenuto |
|---|---|
| Orario classe | griglia settimanale con disciplina, docente/i, aula |
| Orario docente | classi, aule, ore a disposizione, ricevimento, buche evidenziate |
| Orario aula | occupazione |
| Quadro generale | tutte le classi × slot (vista "tabellone") |
| Disponibilità per slot | chi è libero / a disposizione in ogni slot (supporto sostituzioni) |
| Statistiche | buche, giorni liberi, desiderata rispettati, carichi |

Export: **PDF** (stampa per bacheca, per classe e per docente) ed **Excel**. Nessuna integrazione con il registro elettronico.

Import: anagrafiche docenti/classi da CSV/Excel (evitare l'inserimento manuale).

---

## 12. Requisiti non funzionali

- **Web responsive** (consultazione da smartphone per i docenti; editing da desktop).
- **Una sola scuola** per installazione.
- **GDPR**: nessun dato anagrafico degli alunni; il sostegno usa codici anonimi e ore. Restano i dati personali dei docenti (anagrafica, assenze): log accessi e ruoli stretti.
- **Autenticazione**: solo account locali (utenti e ruoli nel database dell'applicazione).
- **Prestazioni**: scuola tipo 15–30 classi, 40–70 docenti; generazione completa entro 1–5 minuti con time limit configurabile.
- **Audit log** di tutte le modifiche a orario, vincoli e sostituzioni.
- Accessibilità WCAG 2.1 AA per le viste pubbliche.

---

## 13. Modello dati (sintesi entità)

```
Impostazioni(durata_ora, conteggio_sostegno_default, priorita_sostituzioni json)
AnnoScolastico(id, inizio, fine)
Periodo(id, anno_id, nome, inizio, fine)                 -- provvisorio/definitivo
Sede(id, nome) ; TempoSpostamento(sede_a, sede_b, minuti)
Slot(id, giorno, ordine, inizio, fine, tipo)            -- griglia unica di istituto
Intervallo(id, giorno, dopo_slot_ordine, inizio, fine)
Aula(id, sede_id, tipo, capacita)
Disciplina(id, codice, nome, classe_concorso, tipo_aula_richiesta, padre_id?)
QuadroOrario(id, nome) ; QuadroOrarioRiga(quadro_id, disciplina_id, ore)
Classe(id, anno_corso, sezione, sede_id, aula_base_id, quadro_id, tempo_scuola, n_alunni, conteggio_sostegno?)   -- override opzionale
Gruppo(id, nome, tipo, n_partecipanti) ; GruppoClasse(gruppo_id, classe_id)
Docente(id, anagrafica, tipo_contratto, tipo_posto, regime, ore_dovute)
DocenteClasseConcorso(docente_id, classe_concorso)
DocenteSede(docente_id, sede_id)
Cattedra(id, docente_id, classe_id|gruppo_id, disciplina_id, ore, compresenza bool)
FabbisognoSostegno(id, classe_id, codice_anonimo, ore_settimanali)
AssegnazioneSostegno(docente_id, classe_id, ore)       -- vincoli S1–S4 in Vincolo
Vincolo(id, tipo, ambito_livello, ambito_ids[], parametri json, severita, peso, attivo, profilo_id)
Orario(id, periodo_id, versione, stato, seed, punteggio, creato_da, creato_il)
Lezione(id, orario_id, cattedra_id, slot_id, durata_slot, aula_id, bloccata)
Assenza(id, docente_id|classe_id, tipo, dal, al, slot_ids?)
Sostituzione(id, data, slot_id, lezione_id, docente_sostituto_id, tipo_copertura, criterio, confermata)
SaldoRecupero(docente_id, minuti_dovuti, minuti_recuperati)
AuditLog(...)
```

---

## 14. Architettura

- **Stack**: HTML, JavaScript, PHP con **Laravel**, database **MariaDB**.
- **Backend**: anagrafiche, vincoli, sostituzioni, autorizzazioni, export PDF/Excel, **code** (driver database) per i job di generazione.
- **Solver**: script Python con OR-Tools CP-SAT sullo stesso server, lanciato dal job: JSON del problema in ingresso → JSON con soluzione, punteggio e diagnostica in uscita.
- **Frontend**: pagine HTML + **JavaScript vanilla** (nessun framework) con griglia drag&drop; avanzamento della generazione via polling.
- Parametri dei vincoli in colonna JSON.

```
[Browser HTML/JS] ──HTTP──> [Laravel] ──job in coda──> [worker] ──JSON──> [solver.py (OR-Tools)]
                               │                                              │
                           [MariaDB] <────────────── risultato ───────────────┘
```

Requisiti del server: PHP, MariaDB, Python 3 con il pacchetto `ortools`, un worker delle code sempre attivo.

---

## 15. Roadmap proposta

| Fase | Contenuto |
|---|---|
| **MVP** | Anagrafiche + import CSV, scansione oraria di istituto, quadri orari, cattedre manuali e proposta automatica, vincoli H1–H10 + catalogo D/T essenziale (D1, D3, D6, T1, T2, T3), generazione con seed, editor griglia, export PDF |
| **Fase 2** | Assenze e sostituzioni con proposta automatica, recupero permessi, versioni e diff |
| **Fase 3** | Gruppi interclasse (2ª lingua, alternativa IRC, LEL, strumento), sostegno con vincoli S1–S4, multi-sede e indisponibilità COE |
| **Fase 4** | Generazione varianti multiple e confronto, rilassamento guidato |

---

## 16. Casi limite da testare

- Docente IRC su 15+ classi con part-time.
- Docente COE con 3 giorni in altra scuola.
- Classi a tempo prolungato con mensa e rientri, nello stesso plesso di classi a tempo normale.
- Seconda lingua articolata in gruppi interclasse (francese/spagnolo) in parallelo.
- Palestra unica con 20 classi × 2h.
- Italiano con blocco minimo 2h ma 5 ore totali (pattern 2+2+1).
- Alunno con 12h di sostegno ripartite tra due docenti; stesso alunno con vincolo "docente unico".
- Uscita didattica di 3 classi: liberazione docenti e assenza accompagnatori.
- Supplente nominato a metà anno che eredita l'orario.
- Variazione d'orario a metà anno con storico conservato.
- Infattibilità volontaria: verificare messaggio diagnostico comprensibile.

---

## 17. Decisioni prese

| Tema | Decisione |
|---|---|
| Perimetro | Solo orari e sostituzioni; niente registro, stipendi, valutazioni |
| Alunni | Non censiti: n. totale per classe, partecipanti per gruppo, sostegno con codici anonimi e ore |
| Sostegno | Ore per alunno coperte anche da più docenti, salvo vincolo |
| Solver | OR-Tools CP-SAT in locale |
| Tenant | Una sola scuola |
| Cattedre | Assegnazione manuale e proposta automatica |
| Export | PDF ed Excel |
| COE | Solo indisponibilità |
| Educatori/OSA | Fuori perimetro |
| Blocchi a cavallo dell'intervallo | Ammessi di default (vietabili con D7) |
| Scansione oraria | Numero di ore, durata e intervalli definiti a livello di scuola, ereditati dalle classi |
| Accesso | Account locali |
| Stack | Laravel, JavaScript vanilla, MariaDB |
| Conteggio sostegno | Configurabile (per alunno / per classe), default di istituto con override per classe |
| Priorità sostituzioni | Ordine di default del §10.2, modificabile |
| Unità oraria | Unica per la scuola (probabilmente 50'); solo report dei minuti da recuperare |
| Notifiche | Nessuna |
