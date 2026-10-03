# Guida all'uso di Orario Scuola

Questa guida è il manuale dell'applicazione. Apri il pannello con il pulsante **Aiuto** in alto a destra o con il tasto **F1**: si apre già sull'argomento della pagina in cui ti trovi. Cambia argomento dal menu oppure scrivi nel campo **Cerca nella guida**: l'elenco mostra le sezioni che contengono le parole cercate e, aprendone una, le evidenzia. Per chiudere il pannello usa la **×**, **Esc** o di nuovo **F1**.

In fondo trovi il **Glossario** (cosa significano i termini scolastici usati) e i **Problemi frequenti**.

## Per iniziare

L'applicazione genera e gestisce l'orario settimanale di una scuola secondaria di primo grado. Il percorso tipico è questo, in ordine:

1. **Sedi** e **Aule**: i plessi dell'istituto e le loro aule (due voci separate del menu).
2. **Discipline**: le materie, con classe di concorso e aula richiesta.
3. **Quadri orari**: quante ore settimanali di ogni disciplina fa una classe.
4. **Docenti** e **Classi**: le anagrafiche.
5. **Cattedre**: chi insegna cosa, in quale classe e per quante ore.
6. **Vincoli** (facoltativo): regole in più per la generazione.
7. **Genera orario**: avvia il calcolo automatico.
8. **Orari**: controlla, correggi a mano ed esporta in PDF.

Perché la generazione possa riuscire, per **ogni classe** devono valere due condizioni:

- le **ore delle cattedre** coincidono con le ore del **quadro orario** della classe;
- il numero di **slot attivi** della classe coincide con le ore del quadro orario (30 ore di quadro = 30 slot attivi).

Per ogni pagina trovi anche una breve guida in alto. Le voci del menu e i pulsanti che non vedi dipendono dal tuo ruolo (vedi *Utenze e ruoli*).

## Sedi e aule

### Sedi

Le **sedi** sono i plessi: se l'istituto ne ha uno solo, basta una sede.

- **Nome**: il nome del plesso.
- **Indirizzo**: facoltativo, solo informativo.

### Aule

Ogni aula appartiene a una sede.

- **Sede**: il plesso in cui si trova.
- **Nome**: es. "Aula 12" o "Palestra".
- **Tipo**: la categoria dell'aula (classe, laboratorio, palestra, aula di musica, ...). Il tipo serve a collegare le discipline che richiedono un'aula speciale: una lezione di Scienze motorie, se la disciplina richiede il tipo "palestra", si svolge in un'aula di quel tipo.
- **Capienza**: quante lezioni possono svolgersi **nello stesso momento** in aule di quel tipo. 1 = una sola classe alla volta; 2 = due classi insieme (es. palestra divisibile). Non è il numero di alunni.

La colonna **Usata da** mostra quali discipline richiedono quel tipo di aula.

### Didattica DADA

Con la didattica DADA le classi non hanno un'aula fissa: **sono gli alunni a spostarsi** nell'aula della disciplina.

1. Vai in **Aule** e crea un'aula.
2. Come tipo scegli **"DADA · nome della disciplina"**.
3. La disciplina viene collegata automaticamente a quell'aula.
4. Nelle classi lascia vuoto il campo **Aula base**.

## Discipline

Il catalogo delle materie insegnate.

- **Codice**: la sigla breve e unica (es. ITA, MAT). È quella che compare nel tabellone PDF.
- **Nome**: es. Italiano, Matematica.
- **Classe di concorso**: il codice di abilitazione dei docenti che la insegnano (es. A022). Serve a proporre le classi di concorso nella scheda del docente; è un dato informativo.
- **Aula richiesta**: l'elenco contiene i tipi di aula già censiti. "Aula della classe" significa nessuna aula speciale: la lezione si svolge dove sta la classe. Se scegli un tipo (es. palestra), le lezioni di quella disciplina occupano un'aula di quel tipo, nei limiti della sua capienza.
- **Sotto-disciplina di**: collega materie insegnate dallo stesso docente (es. Storia e Geografia sotto Italiano). È solo informativo.

## Quadri orari

Un quadro orario è il monte ore settimanale per disciplina (es. "Tempo normale 30h"). Ogni classe ne usa uno.

- **Nome**: es. "Tempo normale 30h" o "Tempo prolungato 36h".
- **Discipline**: ogni riga è una disciplina con le sue **ore settimanali** (1–40). Una disciplina può comparire una sola volta: quelle già inserite sono disattivate nelle altre righe.
- Il **totale** si aggiorna mentre scrivi.
- Aggiungi e togli le righe con i pulsanti della sezione e salva una volta sola, anche alla creazione.
- Un quadro usato da qualche classe **non si può eliminare**.

## Docenti

Per ogni docente puoi indicare:

- **Nome, Cognome**: obbligatori. **Email**: facoltativa, ma se presente deve essere unica.
- **Contratto**: il tipo di rapporto di lavoro.
  - *Tempo indeterminato*: docente di ruolo.
  - *Determinato annuale*: supplenza annuale, fino al 31 agosto.
  - *Determinato fino al termine*: supplenza fino al termine delle attività didattiche (30 giugno).
  - *Supplenza breve*: sostituzione temporanea di un docente assente.
- **Tipo posto**: che tipo di cattedra ricopre.
  - *Comune*: insegna una materia curricolare in una o più classi.
  - *Sostegno*: segue alunni con disabilità, in compresenza con i colleghi. Solo questi docenti si possono assegnare al sostegno di una classe.
  - *Potenziamento*: fa parte dell'organico dell'autonomia; le sue ore servono per progetti e sostituzioni.
  - *IRC*: insegnante di Religione cattolica.
  - *Strumento musicale*: insegna uno strumento nell'indirizzo musicale.
- **Regime**: l'orario di lavoro.
  - *Tempo pieno*: orario di cattedra completo (di norma 18 ore settimanali di lezione).
  - *Part-time orizzontale*: lavora **tutti i giorni**, ma con un orario ridotto in ciascun giorno.
  - *Part-time verticale*: lavora **solo in alcuni giorni** della settimana, con orario pieno nei giorni di servizio.
  - *Part-time misto*: combina i due modelli (giorni ridotti più giorni di assenza).
- **Ore dovute**: le ore settimanali di lezione previste dal contratto (1–24). 18 = cattedra intera; un part-time al 50% ne ha 9. La differenza tra ore dovute e ore assegnate nelle cattedre sono le *ore a disposizione*.
- **COE** (Cattedra Orario Esterna): il docente ha ore anche in un'altra scuola. Per lui l'applicazione gestisce solo le **indisponibilità**: segnala tu gli slot in cui è impegnato nell'altra scuola.
- **Classi di concorso abilitanti**: i codici di abilitazione (es. A022 Lettere, A028 Matematica e scienze, AB25 Inglese). L'elenco è preso dalle discipline.
- **Sedi di servizio**: i plessi in cui insegna.

Importante: **contratto, regime, COE e classi di concorso sono dati anagrafici**: il generatore non li usa da soli. Per far rispettare un part-time o un impegno in un'altra scuola devi segnare le **indisponibilità**.

Nella pagina del docente puoi anche:

- impostare le **indisponibilità**: la griglia degli slot in cui il docente non può avere lezione (part-time, servizio in altre scuole, permessi). Il generatore e l'editor le rispettano sempre;
- gestire le **cattedre**: aggiungi o togli righe e guarda il totale "assegnate / dovute", che diventa ambra se non coincide.

Salva con il pulsante **Salva** in basso a destra: un solo salvataggio vale per tutta la pagina. Puoi importare più docenti insieme da un file **CSV** (colonne: nome, cognome, email, tipo_contratto, tipo_posto, regime, ore_dovute).

## Classi

Ogni classe ha:

- **Anno di corso**: 1ª, 2ª o 3ª.
- **Sezione**: la lettera (A, B, C, ...). Anno e sezione insieme devono essere unici nella stessa sede.
- **Sede**: il plesso della classe.
- **Aula base**: l'aula in cui la classe fa le lezioni che non richiedono un'aula speciale. Lasciala vuota con la didattica DADA.
- **Quadro orario**: il monte ore settimanali per disciplina.
- **Tempo scuola**:
  - *Normale*: 30 ore settimanali, solo la mattina.
  - *Prolungato*: più ore (36, fino a 40) con **rientri pomeridiani**.
- **Numero alunni** (0–35): dato informativo. Gli alunni non sono censiti uno per uno e il numero non cambia il calcolo.

### Rientri pomeridiani

Con il **tempo prolungato** puoi scegliere i **giorni di rientro**, ognuno in modo indipendente (per esempio martedì e giovedì per una classe, lunedì e mercoledì per un'altra). Spuntando un giorno si attivano le sue ore pomeridiane (7ª–9ª) nella griglia degli slot attivi. Il campo è attivo solo con Tempo scuola = Prolungato.

### Slot attivi

La griglia degli **slot attivi** indica le ore della settimana che la classe usa davvero: le righe sono le ore (1ª–9ª), le colonne i giorni. Il generatore riempie esattamente quegli slot, né di più né di meno, quindi **il numero di slot attivi deve coincidere con le ore del quadro orario**. Puoi modificare le singole ore a mano, per casi particolari: in modifica la griglia prevale sui giorni di rientro.

### Cattedre

Nella pagina della classe aggiungi o togli le cattedre (disciplina, docente, ore, compresenza). Il totale mostra le ore assegnate rispetto al quadro orario.

### Sostegno

Gli alunni con sostegno non sono censiti: ogni **fabbisogno** ha solo un codice anonimo (es. `1B-S1`) e le ore settimanali. Poi assegni i **docenti di sostegno** con le loro ore.

- **Codice anonimo**: un'etichetta che identifica un alunno senza nome (es. classe + progressivo). Deve essere unico nella classe.
- **Ore/sett.** del fabbisogno: le ore di sostegno di cui l'alunno ha bisogno.
- **Docente unico**: il fabbisogno deve essere coperto da un solo docente.
- **Conteggio ore**: "per alunno" somma le ore di tutti i fabbisogni (ogni compresenza vale per un alunno); "per classe" prende il fabbisogno più alto (una compresenza copre tutta la classe). Puoi usare il valore predefinito dell'istituto.
- **Docenti assegnati**: ogni docente con le sue ore. L'elenco contiene solo chi ha tipo posto **Sostegno**; lo stesso docente non si può assegnare due volte alla stessa classe.
- Il generatore programma le **compresenze** di sostegno. Il totale "assegnate / richieste" ti dice se le ore bastano.

## Cattedre

Una cattedra assegna un docente a una disciplina per una classe. La pagina **Cattedre** è l'elenco completo, filtrabile per classe e per docente. Puoi anche modificarle dalle pagine di docente e classe.

- **Classe**, **Disciplina**, **Docente**: chi insegna cosa e dove.
- **Ore settimanali** (1–20): quante ore di quella disciplina il docente fa in quella classe.
- **Compresenza**: segnala che la cattedra è svolta insieme a un altro docente nella stessa ora. Per ora è un'indicazione e non cambia il calcolo; le compresenze di sostegno si gestiscono invece dalla scheda della classe.

La stessa combinazione docente + classe + disciplina può comparire una sola volta. La somma delle cattedre di una classe deve coincidere col suo quadro orario; quella di un docente non dovrebbe superare le sue ore dovute.

## Vincoli

I vincoli sono regole aggiuntive per la generazione, oltre a quelle di sistema sempre attive (un docente non può essere in due posti insieme, una classe ha una sola lezione per slot, le indisponibilità sono rispettate, ecc.).

- **Tipo**: quale regola applicare (vedi sotto). I campi mostrati cambiano col tipo.
- **Ambito**: a chi si applica. *Globale* = tutte le classi/tutti i docenti; *Classe* o *Docente* = solo quelli scelti. Le classi si scelgono solo con Ambito = Classe, i docenti solo con Ambito = Docente. Il vincolo più specifico prevale su quello globale.
- **Severità**: *Rigido* va rispettato sempre (se non è possibile l'orario risulta infattibile); *Preferenziale* viene rispettato quando possibile.
- **Peso** (1–100): solo per i preferenziali. Più è alto, più il generatore cerca di evitare di violarlo. Si attiva solo con Severità = Preferenziale.
- **Attivo**: se tolto, il vincolo resta salvato ma non viene considerato.
- **Nota**: promemoria libero.

Tipi disponibili:

- **Blocco consecutivo minimo (D1)**: una disciplina in blocchi di almeno N ore consecutive. Campi: disciplina, *min ore consecutive* (2–6), *n. blocchi minimi* (1–5).
- **Max ore/giorno per disciplina (D3)**: limite di ore al giorno della stessa disciplina. Campi: disciplina, *max ore/giorno*.
- **Fascia oraria vietata/preferita (D6)**: ore in cui una disciplina non va (o è preferibile) collocata. Campi: disciplina, *tipo fascia* (vietata/preferita), gli *slot* interessati.
- **Giorno libero (T2)**: un docente ha uno o più giorni liberi. Campi: *n. giorni liberi richiesti* (1–3), *giorno preferito* facoltativo.
- **Max ore buche (T3)**: limite alle *buche* (ore vuote tra due lezioni dello stesso docente nello stesso giorno). Campi: *max buche/giorno*, *max buche/settimana*.

## Genera orario

La generazione avviene **in background**: avvia il calcolo e segui l'avanzamento nella pagina.

1. Controlla in alto lo stato del **worker di coda**, il programma che esegue i calcoli. Se è "fermo", premi **Avvia**: senza worker le generazioni restano in coda. Per fermarlo usa **Ferma**: termina prima il job in corso. Se lo vedi "in arresto" puoi già riavviarlo.
2. Premi **Nuova generazione** e compila:
   - **Tempo limite** (10–900 secondi): per quanto tempo il generatore può cercare un orario migliore. Più tempo, orari generalmente migliori.
   - **Seed** (facoltativo): un numero che rende il risultato riproducibile. Stessi dati e stesso seed producono lo stesso orario. Vuoto = casuale; il seed usato viene sempre registrato.
3. Segui lo **stato** della generazione:
   - *In coda*: aspetta che il worker la prenda in carico (se non parte, il worker è fermo).
   - *In corso*: il calcolo è in esecuzione; la barra mostra l'avanzamento.
   - *Completata*: l'orario è pronto e compare tra gli **Orari**.
   - *Infattibile*: non esiste un orario valido con i dati e i vincoli attuali (o il tempo è finito senza trovarne uno). La pagina elenca **quali vincoli o risorse sono in conflitto**.
   - *Fallita*: errore tecnico durante il calcolo.

Il **punteggio** di un orario somma le penalità dei vincoli preferenziali violati: **0 = tutti rispettati, più è basso meglio è**.

Prima del calcolo l'applicazione controlla i dati: ore delle cattedre contro quadro orario, slot attivi contro quadro orario, ore dei docenti contro slot disponibili, capacità delle aule e ore di sostegno. I problemi trovati vengono mostrati senza avviare il calcolo.

## Orari e modifica manuale

La pagina **Orari** elenca gli orari prodotti, con **periodo**, **versione**, **stato** (oggi gli orari nascono in *bozza*) e **punteggio**. Da lì puoi:

- aprire la griglia di una classe (modificabile) o di un docente (sola lettura), con le **select di ricerca**: scrivi parte del nome per trovare la voce;
- scaricare il **tabellone generale in PDF**;
- eliminare un orario selezionandolo con la casella a sinistra (la generazione resta nello storico).

### Griglia della classe

- **Trascina** una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si **scambiano**.
- Il menu dentro la lezione cambia **materia e/o docente** (cerca per materia o per docente).
- **Blocca/Sblocca**: una lezione bloccata non si sposta, non si scambia e non si modifica.
- **Annulla ultima modifica** ripristina l'ultima operazione.
- L'esito delle modifiche resta nel **pannello degli avvisi** sopra la griglia finché non lo azzeri. Un **errore** significa che l'operazione è stata rifiutata (per esempio lo spostamento in uno slot in cui il docente è indisponibile); un **avviso** significa che è stata applicata ma da controllare.

### Esportare in PDF

- **Per classe** e **per docente**: dal pulsante "Esporta PDF" della griglia. Le righe delle ore dopo l'ultima usata non compaiono.
- **Tabellone generale**: un solo foglio A3, una riga per classe e le colonne divise per giorno, tutte della stessa larghezza. Mostra la sigla della materia e il cognome del docente (troncati con "…" se lunghi); i docenti di **sostegno** in compresenza compaiono come "S Cognome". In fondo c'è la legenda delle sigle.

## Utenze e ruoli

Le utenze sono account locali. Solo l'**amministratore** vede la voce **Utenze**, dove crea, modifica ed elimina gli accessi.

Campi di un'utenza:

- **Nome**: come viene mostrato nell'applicazione.
- **Email**: è il nome utente per accedere; deve essere unica.
- **Ruolo**: cosa può fare (vedi tabella).
- **Docente collegato**: solo per il ruolo Docente, collega l'accesso alla sua scheda.
- **Password**: almeno 8 caratteri. In modifica, lasciandola vuota, quella esistente non cambia.

| Ruolo | Cosa può fare |
| --- | --- |
| Amministratore | Tutto, comprese le utenze |
| Referente Orario | Anagrafiche, vincoli, generazione ed editor dell'orario |
| Segreteria | Consulta tutto; gestisce docenti e classi |
| Dirigente Scolastico | Consulta tutto |
| Referente Sostituzioni | Consulta tutto |
| Docente | Accesso base, collegato alla propria anagrafica |

Non puoi eliminare la tua utenza né toglierti il ruolo di amministratore.

## Consigli d'uso

- **Modali**: le schede di creazione e modifica semplici si aprono in una finestra. Si chiude con **×**, **Annulla** o **Esc**; un click fuori non la chiude, così non perdi i dati. Salvando la finestra si chiude. **Salva** è sempre in basso a destra. Docenti e classi si modificano su pagina intera.
- **Eliminare**: nelle tabelle spunta le righe e premi **Elimina selezionati**. Il pulsante è disabilitato finché non selezioni almeno una riga. Gli elementi ancora in uso non vengono eliminati e ti viene detto quanti.
- **Campi obbligatori**: sono contrassegnati da un asterisco rosso **\***; senza compilarli non si può salvare. Alcuni lo diventano solo in certe condizioni (per esempio il peso di un vincolo, obbligatorio solo se la severità è Preferenziale).
- **Controlli disabilitati**: se un campo è grigio e vedi un'icona **i**, passaci sopra: spiega cosa impostare per attivarlo.
- **Righe ripetibili** (cattedre, discipline del quadro, sostegno): aggiungi o rimuovi righe con i pulsanti della sezione e salva con il pulsante unico in basso a destra.
- **Ore pomeridiane**: ogni giorno offre le ore 7ª–9ª; sta alle classi attivarle con i rientri.

## Glossario

- **Ambito** (vincoli): a chi si applica un vincolo (tutti, classi o docenti scelti).
- **Aula base**: l'aula di riferimento di una classe.
- **Buca**: ora vuota tra due lezioni dello stesso docente nello stesso giorno.
- **Cattedra**: assegnazione di un docente a una disciplina in una classe, con un numero di ore settimanali. Anche, nel linguaggio comune, l'insieme delle ore di un docente (cattedra intera = 18 ore).
- **Classe di concorso**: il codice che identifica l'abilitazione all'insegnamento di una materia (es. A022 Lettere, A028 Matematica e scienze, AB25 Inglese, A049 Scienze motorie, A060 Tecnologia; AA25, AC25, AD25 sono le seconde lingue).
- **COE**: Cattedra Orario Esterna, docente con ore anche in altre scuole.
- **Compresenza**: due docenti presenti insieme nella stessa classe nella stessa ora (tipicamente docente curricolare più docente di sostegno).
- **DADA**: didattica per ambienti di apprendimento: le classi non hanno un'aula fissa, gli alunni si spostano nell'aula della disciplina.
- **Indisponibilità**: slot in cui un docente non può avere lezione.
- **IRC**: Insegnamento della Religione Cattolica.
- **Orario**: il risultato di una generazione: l'elenco delle lezioni nei vari slot.
- **Ore a disposizione**: ore dovute dal docente non coperte da lezioni (ore dovute − ore di cattedra); servono ad esempio per le sostituzioni.
- **Ore dovute**: ore settimanali di lezione previste dal contratto del docente.
- **Part-time orizzontale / verticale / misto**: orario ridotto tutti i giorni / lavoro solo in alcuni giorni / combinazione delle due forme.
- **Potenziamento**: organico dell'autonomia; ore per progetti e sostituzioni.
- **Quadro orario**: il monte ore settimanale per disciplina di una classe.
- **Rientro pomeridiano**: giorno in cui una classe a tempo prolungato fa lezione anche il pomeriggio.
- **Seed**: numero che rende riproducibile una generazione.
- **Slot**: una singola ora della settimana (giorno + ora, es. martedì 3ª ora). La scansione oraria di istituto è l'insieme di tutti gli slot.
- **Sostegno**: supporto agli alunni con disabilità; nell'applicazione si indicano solo il fabbisogno orario (con codici anonimi) e i docenti assegnati.
- **Tabellone**: il PDF con l'orario di tutte le classi su un unico foglio.
- **Tempo normale / prolungato**: classi con 30 ore solo al mattino / con più ore e rientri pomeridiani.
- **Vincolo rigido / preferenziale**: regola da rispettare sempre / regola da rispettare quando possibile, con un peso.
- **Worker di coda**: il programma di servizio che esegue in background le generazioni dell'orario.

## Problemi frequenti

- **La generazione resta "in coda".** Il worker di coda è fermo: in **Genera orario** premi **Avvia**.
- **La generazione è "infattibile".** Leggi i messaggi nella pagina della generazione. Le cause più comuni: le ore delle cattedre di una classe non coincidono con il quadro orario; gli slot attivi della classe non coincidono con le ore del quadro; a un docente sono state assegnate più ore degli slot in cui è disponibile; non ci sono abbastanza aule di un tipo; due vincoli rigidi si contraddicono; le ore di sostegno assegnate non coprono il fabbisogno.
- **Non riesco a eliminare un elemento.** È ancora in uso: ad esempio un quadro orario usato da classi. Il messaggio dopo l'eliminazione dice quanti elementi non sono stati eliminati.
- **Ho eliminato un docente o una classe per errore.** Con loro vengono eliminate anche le cattedre collegate e le lezioni degli orari già generati che le usavano. Controlla sempre la conferma prima di eliminare.
- **Non vedo un pulsante o una voce del menu.** Dipendono dal tuo ruolo: chiedi all'amministratore se ti serve un ruolo diverso.
- **Un campo è grigio e non posso modificarlo.** Passa il mouse sull'icona **i** accanto: dice cosa impostare per attivarlo (per esempio il peso di un vincolo richiede Severità = Preferenziale).
- **Una modifica all'orario ha prodotto un errore o un avviso.** Leggi il pannello sopra la griglia: un errore vuol dire che la modifica non è stata fatta, un avviso che è stata fatta ma va controllata. Restano lì finché non li azzeri.
- **Il PDF non mostra le ore del pomeriggio.** Le ore senza lezioni non compaiono: se nessuna classe ha lezione il pomeriggio, le colonne sono nascoste.
