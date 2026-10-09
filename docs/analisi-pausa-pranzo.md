# Analisi — Pausa pranzo (mensa)

Data: 9 ottobre 2026 · Versione applicazione di riferimento: 0.37.x (con le modifiche non ancora committate su `pausa_dopo_ora`)

Stato: **proposta aggiornata con le decisioni del 9 ottobre** (§10); resta una sola verifica sui dati (§10.2).

## 1. Obiettivo

La pausa pranzo del tempo prolungato deve:

1. svolgersi in un'**aula** (refettorio, auditorium…);
2. **contare nel monte ore** del docente che la sorveglia;
3. **comparire nei PDF** (classe, docente, aula, tabellone);
4. essere **intuitiva da configurare**: oggi funziona, ma richiede di trattare la mensa come una *disciplina* e di togliere un'ora dalla scansione, cosa che non si capisce senza leggere la guida.

Questo documento descrive come funziona oggi, cosa non va (con i dati reali della scuola) e propone un modello unico.

---

## 2. Come funziona oggi

Oggi la mensa è rappresentata da **due meccanismi paralleli**, nati in momenti diversi, che si sovrappongono.

### 2.1 Meccanismo A — la pausa della scansione + assistenza

| Dove | Cosa |
|---|---|
| `slot.ricreazione_minuti/_nome/_conteggio/_aula_id` | pausa dopo un'ora, con nome («Pausa pranzo»), durata, valore per il docente (scatti di 15') e aula di tipo `pausa` |
| `assistenze_pausa (docente, giorno, ordine)` | il docente sorveglia quella pausa quel giorno (scheda docente) |
| `AssistenzaPause::ore()` | somma al monte ore, **una volta per giorno e pausa** |
| `OrarioPdfExporter::sorveglianti()` | riga della pausa nei PDF con chi sorveglia |

Pregi: è il modello giusto per il **docente** (conta una volta sola anche se sorveglia più classi insieme) e per l'**aula** (è sulla pausa). Limiti: **non dice quali classi** sorveglia il docente, e non contribuisce al quadro orario della classe.

### 2.2 Meccanismo B — la disciplina «senza ora»

| Dove | Cosa |
|---|---|
| `discipline.senza_slot` | la disciplina «Pranzo» (PRA) non è una lezione |
| `discipline.pausa_dopo_ora` (non committato) | in quale pausa si svolge, per i PDF |
| quadro orario «Tempo prolungato 36h» | riga PRA = 2 ore |
| `cattedre` PRA | docente + classe + ore |
| `PreValidator::oreQuadroVsCattedre()` | slot attivi = quadro − ore «senza ora»; la cattedra in compresenza non si somma |
| `AssistenzaPause::oreCattedreDaEscludere()` | se il docente ha anche assistenze, le cattedre PRA non si sommano (per non contare due volte) |
| `ProblemBuilder`, `ControlloOrario`, select della griglia, `Classe`, `PreValidator` | filtri `reject(senza_slot)` sparsi |

Pregi: fa tornare il **quadro della classe** (36 = 34 lezioni + 2 mensa) e lega un docente a una **classe**. Limiti: vedi §3.

### 2.3 Come si combinano

La guida (*Discipline → Esempio: la mensa*) descrive tre casi (A: un docente per classe, B: due docenti con compresenza, C: docenti su più classi → assistenze + una cattedra fittizia per far tornare il quadro). Per configurare la mensa l'utente deve toccare **sei pagine**: Scansione oraria, Aule, Discipline, Quadri orari, Classi (slot attivi), Cattedre/Docenti.

---

## 3. Dati reali (sede «Scuola Secondaria Gavazzi», letti dal database)

**Scansione (uguale per tutti i giorni)**

| Ora | Orario | Pausa dopo |
|---|---|---|
| 6ª | 13:10–14:00 | **Pausa pranzo 50'** (14:00–14:50), aula Auditorium |
| 7ª | 14:50–15:40 | — |
| 8ª | 15:40–16:30 | — |
| 9ª | 16:30–17:20 | — |

**Classi a tempo prolungato** (1C, 2C, 3C): quadro 36h con 2h di PRA, rientro **lunedì e mercoledì**, slot attivi 34 = ore 1–6 tutti i giorni + **8ª e 9ª** nei giorni di rientro. **La 7ª ora è spenta.**

**Cattedre PRA**

| Classe | Docente | Ore | Compresenza |
|---|---|---|---|
| 1C | Costanzo | 2 | no |
| 2C | Costanzo | 1 | no |
| 2C | Paladini | 1 | no |
| 3C | Paladini | 1 | no |
| 3C | Chianello (sostegno) | 1 | no |

**Assistenze alle pause**: Federico e Paladini, lunedì, `ordine = 7`.

### Anomalie che emergono dai dati

1. **Pausa pranzo e 7ª ora spenta insieme.** La pausa va dalle 14:00 alle 14:50 *e* la 7ª (14:50–15:40) non è attiva per nessuna classe: nell'orario in bozza (v11) le lezioni del pomeriggio cadono in 8ª e 9ª (15:40–17:20), con un'ora vuota tra la fine della pausa e la prima lezione. Dal registro attività: la pausa di 50' dopo la 6ª c'era già (oggi le è stato solo dato il nome «Pausa pranzo»), quindi la 7ª è stata spenta non perché fosse il pranzo, ma perché il rientro spunta **tre** ore (7ª–9ª) per giorno: 30 + 2 × 3 = 36 slot, mentre con 2h di PRA nel quadro ne servono 34. Togliere un'ora per giorno era obbligatorio, e la scelta è caduta sulla 7ª. È il passo «togliere la settima ora» che rende tutto poco intuitivo; se le lezioni reali sono 14:50–16:30, **le ore da spegnere sono le 9ª, non le 7ª** (vedi §7).
2. **Assistenze orfane.** Le due assistenze puntano alla pausa «dopo la 7ª», che non esiste più: non compaiono nei PDF e valgono 0 nel monte ore, **in silenzio** (`AssistenzaPause::elenco()/ore()` le scartano). Nessun avviso le segnala.
3. **La cattedra non dice il giorno.** 2C ha Costanzo 1h e Paladini 1h: chi c'è il lunedì e chi il mercoledì? Il dato non esiste; i PDF (`mensaDellaClasse`, `celleMensa`) stampano **entrambi i docenti in entrambi i giorni**.
4. **Doppio conteggio possibile.** Costanzo ha 3h di mensa (1C 2h + 2C 1h). Se il lunedì 1C e 2C mangiano insieme nell'Auditorium con lei, l'ora reale è una, non due. Il sistema non può saperlo: la cattedra è per classe.
5. **Compresenza ambigua.** Le cattedre PRA non sono in compresenza perché i due docenti coprono giorni diversi; ma lo stesso flag serve (caso B della guida) per due docenti nello stesso giorno. Due situazioni diverse, stessa forma, decise a mano.
6. **Disciplina «Pranzo» con `tipo_aula_richiesto = classe`**: l'aula della disciplina non ha senso (l'aula vera è sulla pausa).
7. **Configurazione cambiata più volte in giornata** (registro attività del 9 ottobre): cattedre PRA in 2C messe in compresenza alle 12:58 e tolte alle 13:52, ore da 2 a 1, una cattedra PRA in 1A (tempo normale) eliminata, codice rinominato da «Pranzo» a «PRA», `pausa_dopo_ora` impostata alle 16:23. È il segno concreto che il modello non si capisce senza procedere per tentativi.

### Altri dati utili

- **Auditorium** (aula della pausa): tipo `pausa`, piano 3, **capienza 3** = tre classi contemporaneamente. Il lunedì e il mercoledì in mensa ci sono 1C, 2C e 3C: la capienza basta.
- **Laboratori pomeridiani**: nessuno censito, quindi la 7ª ora spenta non è occupata da altro.
- **Disciplina «senza ora»**: l'unica nel database è PRA. Nel codice, nei test (`DisciplinaSenzaOraTest`, `GenerazioneCompletaTest`, `AssistenzaPauseTest`…), in `CLAUDE.md`, nella guida e nella storia dei commit (nata con 9c275d2 «Discipline senza ora di lezione (mensa)») è usata e spiegata **solo per la mensa**. Nessun seeder la usa.

---

## 4. Perché non è intuitivo

| Concetto per la scuola | Come va inserito oggi | Problema |
|---|---|---|
| «Il lunedì e il mercoledì la 1C pranza in auditorium dalle 14:00 alle 14:50» | pausa in scansione + rientri della classe | ok |
| «Il tempo prolungato fa 36 ore» | riga PRA nel quadro + slot attivi = 36 − 2 | sottrazione da ricordare; la mensa sembra una materia |
| «Costanzo sorveglia la 1C il lunedì» | cattedra PRA 1C (senza giorno) **oppure** assistenza lunedì (senza classe) | due strade, nessuna completa |
| «Costanzo fa 1 ora di mensa a settimana» | ore della cattedra **oppure** conteggio della pausa, con regola di esclusione | il totale dipende da quale strada si è usata |

Il nodo è che la mensa è **un evento della scansione (giorno × pausa) a cui partecipano classi e docenti**, mentre oggi la si spezza in una *disciplina* (per far tornare i conti delle classi) e un'*assistenza* (per far tornare i conti dei docenti).

---

## 5. Proposta: la mensa come pausa con classi e docenti

Un solo concetto, il **turno di mensa** = (pausa, giorno) con le sue classi e i suoi docenti. Tutto il resto si ricava.

### 5.1 Modello dati

| Modifica | Dettaglio |
|---|---|
| `slot.ricreazione_mensa` (bool, default false) | in Scansione oraria la pausa si marca **«È la mensa»**. Le altre pause restano ricreazioni. Serve per distinguere chi ci partecipa (sotto). Non sono previsti turni: **al massimo una pausa per sede** è la mensa (validazione in `ScansioneOrariaRequest`). Il flag vale per le pause *dopo* un'ora, non per quella prima della 1ª. |
| **Classi in mensa**: ricavate | una classe è in mensa in un giorno se ha ore attive **dopo** la pausa mensa in quel giorno (è già la regola usata oggi per i PDF). Nessuna tabella nuova. |
| `assistenza_pausa_classe (assistenza_pausa_id, classe_id)` | pivot nuova: **quali classi** sorveglia il docente in quel turno. Vuota = tutte le classi in mensa quel giorno (o, per le ricreazioni, sorveglianza generale come oggi). |
| `quadri_orari.ore_mensa` (int, default 0) | il quadro dichiara le ore di mensa a parte: `ore_totali = ore delle discipline + ore_mensa`. Le righe del quadro sono **solo discipline vere**. Una mensa vale per la classe quanto per il docente: «Conta per il docente» della pausa (60' = 1 ora). |
| `discipline.senza_slot`, `discipline.pausa_dopo_ora` | da **eliminare** dopo la migrazione dei dati (vedi §7). |

Monte ore del docente: **solo dalle assistenze**, come oggi (`AssistenzaPause::ore()`), una volta per giorno qualunque sia il numero di classi sorvegliate, con il valore impostato in **Scansione oraria** (colonna «Conta per il docente»; nessun valore sulla cattedra). Vale anche per il **docente di sostegno**: l'ora di mensa si somma al suo «Assegnate / dovute» insieme alle ore di sostegno, come già fa oggi la dashboard. Sparisce `oreCattedreDaEscludere()`.

### 5.2 Regole e controlli (PreValidator / dashboard)

| Controllo | Oggi | Proposta |
|---|---|---|
| Slot attivi | quadro − ore «senza ora» | **ore delle discipline del quadro** (stessa regola del tempo normale) |
| Ore di mensa della classe | implicito nelle cattedre | giorni in mensa × conteggio della pausa = `quadro.ore_mensa` (Gavazzi: 2 giorni × 60' automatici per 50' di pausa = 2h ✓); altrimenti avviso con «Correggi» → scheda classe (rientri) |
| Classe in mensa senza docente | nessuno | avviso per classe e giorno |
| Assistenza su pausa inesistente | scartata in silenzio | **avviso** con link alla scheda del docente (risolve l'anomalia 2) |
| Docente in mensa indisponibile tutto il giorno | esiste | resta |
| Capienza dell'aula della mensa | nessuno | classi in mensa quel giorno > `aule.capienza` (classi contemporanee, già impostata nell'aula) → avviso con link all'aula. Auditorium oggi: capienza 3, 3 classi in mensa → ok |

### 5.3 Interfaccia

1. **Scansione oraria**: sulla pausa, spunta **«È la mensa»** accanto a nome, conteggio e aula (già presenti).
2. **Quadri orari**: campo **«Ore di mensa»** sotto le righe; il totale mostra «34 + 2 di mensa = 36».
3. **Pagina «Mensa»** (nuova, voce di menu dopo *Scansione oraria*, consultazione `consulta`, modifica `gestisci-anagrafica`, mini guida `<x-guida>`; se nessuna pausa è marcata mensa la pagina lo dice con il link alla Scansione oraria): una **griglia classi × giorni** con solo le celle in cui la classe è in mensa; in ogni cella una select multipla dei docenti. Si vede a colpo d'occhio chi manca. Salva su `assistenze_pausa` + pivot (un'assistenza per docente/giorno, con le classi scelte). Sopra, il riepilogo per giorno: classi, docenti, aula, capienza.
4. **Scheda docente**: l'assistenza alle pause resta per le ricreazioni; per la mensa mostra in sola lettura «Lun · Mensa 14:00–14:50 · 1C, 2C» con link alla pagina Mensa (un solo posto in cui si modifica).
5. **Scheda classe**: il riquadro degli slot attivi confronta con le ore delle discipline; sotto, «Mensa: lunedì (Costanzo), mercoledì (Paladini)» in sola lettura.

### 5.4 PDF

| Foglio | Riga/colonna della mensa |
|---|---|
| Classe | nei giorni in mensa: docenti **di quella classe in quel giorno** (pivot), aula con piano |
| Docente | nella riga della pausa: classi sorvegliate quel giorno («1C, 2C») |
| Aula (refettorio) | foglio anche per l'aula della pausa: per giorno classi e docenti |
| Tabellone per classe | colonna della mensa per giorno con il cognome del docente (oggi `celleMensa`, ma per giorno esatto) |

La sezione «Mensa e attività senza ora» sotto le griglie (`senzaOra()`) sparisce: l'informazione è nella riga della pausa.

### 5.5 Solver

Nessuna modifica: la pausa non è uno slot, quindi non c'è conflitto tra mensa e lezioni. L'assegnazione automatica dei sorveglianti (vincolo **C3**) resta fuori, come da roadmap. Rimangono da togliere i `reject(senza_slot)` in `ProblemBuilder` (diventano inutili).

### 5.6 Rientri pomeridiani

È l'origine del «togliere la settima ora» (anomalia 1): il rientro spunta sempre 7ª–9ª, tre ore per giorno, e l'utente deve spegnerne una a mano senza sapere quale. Con il pranzo come pausa e il quadro che dichiara le ore di lezione a parte, la regola diventa calcolabile:

- ore pomeridiane per rientro = (ore di lezione del quadro − ore attive del mattino) ÷ giorni di rientro (es. (34 − 30) ÷ 2 = **2**);
- spuntando un rientro, `form-modifica.js` attiva le prime N ore **subito dopo la mensa** (7ª–8ª) e lascia spenta la 9ª;
- se la divisione non è esatta (es. 3 rientri per 4 ore) spunta per eccesso e il riquadro «ore attese / spuntate», che esiste già, segnala la differenza da correggere a mano.

Serve che la vista conosca le ore di lezione del quadro (già in `conta-slot.js` come «ore attese»).

---

## 6. Alternative valutate

| Alternativa | Perché no |
|---|---|
| **Tenere la disciplina «senza ora» e aggiungere il giorno alla cattedra** | resta il doppio conteggio quando un docente sorveglia più classi insieme, e la mensa continua a sembrare una materia; servirebbe un'eccezione per ogni nuova vista |
| **Mensa come slot (ora di lezione «Pranzo»)** | è la soluzione di partenza: occupa uno slot, il solver la deve piazzare, la durata è quella di un'ora e il conteggio per il docente è legato alla cattedra |
| **Tabella nuova `turni_mensa (classe, giorno, docente)`** | equivalente alla pivot, ma duplica assistenze, CSV, audit, PDF e monte ore che `assistenze_pausa` ha già |
| **Nessun `ore_mensa` nel quadro** (tempo scuola = solo ore di lezione) | più semplice, ma il quadro «36h» non tornerebbe più a 36 e si perde il controllo che ogni classe a tempo prolungato abbia le sue mense |

---

## 7. Migrazione dei dati

1. **Scansione**: aggiungere `ricreazione_mensa`; impostarla a true dove `ricreazione_nome` contiene «mensa» o «pranzo», oppure dove esiste una disciplina `senza_slot` con `pausa_dopo_ora` su quella pausa.
2. **Quadri**: per ogni quadro, `ore_mensa` = somma delle righe di discipline `senza_slot`; quelle righe si eliminano (`ore_totali` invariato).
3. **Cattedre «senza ora» → assistenze**: la cattedra non ha il giorno, quindi la conversione è automatica **solo** quando non è ambigua:
   - un solo docente per la classe e ore = giorni di rientro → assistenza in tutti i giorni di rientro con la classe (es. 1C/Costanzo → lun + mer);
   - altrimenti (2C, 3C) → nessuna conversione: la pagina Mensa mostra le celle vuote e il controllo «classe in mensa senza docente» le segnala.
   Le assistenze già esistenti dello stesso docente nello stesso giorno si **uniscono** (così Costanzo lunedì con 1C e 2C conta un'ora sola).
4. **Assistenze orfane**: non si cancellano; compaiono nel nuovo avviso.
5. Cattedre e disciplina «senza ora» si eliminano (audit); poi colonne `senza_slot` e `pausa_dopo_ora` e i filtri relativi. Migrazione reversibile (`down` ricrea le colonne; i dati delle cattedre restano nel backup ZIP).
6. **CSV**: colonna facoltativa `mensa` nella scansione, `ore_mensa` nei quadri, `classi` (elenco con `|`) nelle assistenze (`ListeCsvAggiuntive`); i file vecchi restano validi. Le colonne `senza_slot`/`pausa_dopo_ora` delle discipline si **ignorano** in import. `CsvCompletoTest` e `DatiTest` da aggiornare; lo ZIP include la pivot da solo.
7. **Dati della Gavazzi** (a mano, §10.2): l'ora vuota sparisce. La pausa pranzo in Scansione oraria prende la **durata reale del pranzo**, le ore del pomeriggio scorrono da sole e le classi a tempo prolungato accendono le prime ore dopo la mensa (7ª–8ª) e spengono la 9ª; poi si rigenera l'orario. Le due assistenze orfane (Federico, Paladini, lunedì «dopo la 7ª») vanno riassegnate alla mensa dalla nuova pagina.

---

## 8. Impatto sul codice

| Area | File |
|---|---|
| Migrazioni | `slot` (+`ricreazione_mensa`), `quadri_orari` (+`ore_mensa`), nuova pivot, conversione dati, rimozione `senza_slot`/`pausa_dopo_ora` |
| Modelli/servizi | `Slot`, `QuadroOrario`, `AssistenzaPausa` (+`classi()`), `AssistenzaPause` (pausa mensa, classi in mensa per giorno, avviso orfani; via `oreCattedreDaEscludere`), `PreValidator`, `Classe` (via il calcolo «senza ora»), `ScansioneOraria`, `CopiaDaSede` (flag mensa e `ore_mensa`), `PromptOrario` (regola e glossario), `ListeCsv`/`ListeCsvAggiuntive` |
| Rimozioni | filtri `senza_slot` in `ProblemBuilder`, `ControlloOrario`, `OrarioController`, `PreValidator`, `DashboardController`/`DocenteController` (`withSum ore_senza_ora`), `form-modifica.js` (`data-senza-ora-con-assistenza`, `data-senza-compresenza`), select cattedre (`data-senza-slot`) |
| Nuovo | `MensaController` + vista griglia (pagina dedicata, voce di menu e sezione `## Mensa` della guida), JS per la griglia (riusa `select-ricerca`) |
| PDF | `OrarioPdfExporter` (`sorveglianti` con classi, `mensaDellaClasse`/`celleMensa` dalle assistenze, foglio aula della pausa), `griglia.blade.php`, `tabellone.blade.php` |
| Test | `PausaPrimaTest`, `ExportPdfTest`, `PreValidator*`, `GenerazioneCompletaTest` (la mensa esce dalle cattedre), `CsvCompletoTest`, `DatiTest`, test della migrazione dei dati |
| Documenti | `docs/guida-utente.md` (sezione Mensa al posto di «Esempio: la mensa»), `CLAUDE.md`, nota di implementazione nel §4 dell'analisi (proposta, da approvare) |

Le modifiche **non committate** su `pausa_dopo_ora` diventerebbero superflue: si possono committare ora (funzionano) e togliere con questo lavoro, oppure scartarle.

---

## 9. Piano a passi

| Passo | Contenuto | Versione |
|---|---|---|
| 1 | Avviso per assistenze orfane (utile subito, indipendente dal resto) | patch |
| 2 | Flag «È la mensa», `ore_mensa` nei quadri, pivot classi; controlli nuovi in `PreValidator` | minor |
| 3 | Pagina Mensa (griglia classi × giorni), scheda docente/classe in sola lettura, rientro che spunta le ore giuste | minor |
| 4 | PDF e tabellone dalle assistenze | minor |
| 5 | Migrazione dei dati e rimozione di `senza_slot`/`pausa_dopo_ora`, guida e CSV | minor |

---

## 10. Decisioni e verifiche

### 10.1 Decisioni (9 ottobre)

| # | Domanda | Decisione |
|---|---|---|
| 1 | Dove si decide quanto vale la mensa nel monte ore | In **Scansione oraria**, colonna «Conta per il docente» della pausa (già esistente). Nessun valore sulle cattedre. |
| 2 | `senza_slot` serve ad altro? | Verificato su codice, database, documenti e storia dei commit (§3, «Altri dati utili»): **solo per la mensa**. Si elimina con `pausa_dopo_ora`. |
| 3 | Dove si assegnano i docenti | **Pagina dedicata «Mensa»** (§5.3): la Scansione oraria è già fitta e riguarda tutte le pause; la mensa ha classi e docenti per giorno, che stanno bene in una griglia propria. |
| 4 | Docente di sostegno in mensa | Come ogni docente: l'ora di mensa **si somma al suo monte ore** (assistenza). |
| 5 | Capienza della mensa | `aule.capienza` dell'aula della pausa = classi contemporanee; controllo con avviso (§5.2). |
| 6 | Turni di mensa | Non previsti: una sola pausa mensa per sede, classi ricavate dai rientri. Se un giorno serviranno turni, si aggiunge la scelta esplicita delle classi per pausa. |

### 10.2 L'ora vuota (decisione del 9 ottobre)

**Com'è oggi.** La 7ª ora (14:50–15:40) resta **spenta apposta** per il pranzo: il pranzo è rappresentato da un'ora vuota, in aggiunta alla pausa di 50' dopo la 6ª. L'utente l'ha confermato e non gli piace, e ha ragione:

- l'ora vuota non ha nome, aula né docenti: nei PDF compare come un buco, e la mensa si ricostruisce solo dalla riga della pausa;
- vanno tenuti allineati due dati (pausa di 50' + 7ª spenta) che descrivono la stessa cosa;
- ogni classe deve spegnere a mano la stessa ora, e il generatore la vede come un'ora qualsiasi della scansione (per gli altri, per i laboratori, è disponibile);
- per i docenti l'ora vuota non conta: conta solo la pausa.

**Proposta.** Il pranzo è **solo la pausa**, con la sua durata reale; nessuna ora della scansione resta vuota apposta. In Scansione oraria si scrive la durata del pranzo nei minuti della pausa dopo la 6ª e le ore del pomeriggio si spostano da sole (lo fa già `scansione-oraria.js`). Le ore 7ª–9ª diventano tutte ore di lezione vere, e il rientro accende le prime che servono (§5.6).

| Pranzo reale | Pausa dopo la 6ª | Lezioni del pomeriggio (rientro) | 9ª |
|---|---|---|---|
| 14:00–14:50 | 50' | 7ª–8ª, 14:50–16:30 | spenta |
| 14:00–15:40 (con l'ora vuota di oggi) | 100' | 7ª–8ª, 15:40–17:20 | spenta |

«Conta per il docente» resta indipendente dalla durata: anche con un pranzo di 100' si può far valere 60' (1 ora) nel monte ore.

**Da confermare:** quanto dura davvero il pranzo, cioè quale delle due righe della tabella vale per la Gavazzi.
