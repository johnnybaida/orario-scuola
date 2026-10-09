# Guida all'uso di Orario Scuola

Questa guida è il manuale dell'applicazione. Apri il pannello con il pulsante **Aiuto** in alto a destra o con il tasto **F1** (anche quando hai una finestra di modifica aperta: la guida compare sopra di lei, Esc la chiude senza chiudere la finestra): si apre già sull'argomento della pagina in cui ti trovi. Cambia argomento dal menu oppure scrivi nel campo **Cerca nella guida**: l'elenco mostra le sezioni che contengono le parole cercate e, aprendone una, le evidenzia. Per chiudere il pannello usa la **×**, **Esc** o di nuovo **F1**.

Stai usando l'applicazione come **{ruolo}**: la guida mostra solo ciò che puoi vedere e fare con questo ruolo.

In fondo trovi il **Glossario** (cosa significano i termini scolastici usati) e i **Problemi frequenti**.

## Per iniziare
<!-- sezione: consulta -->

L'applicazione genera e gestisce l'orario settimanale di una scuola secondaria di primo grado. Il percorso tipico è questo, in ordine. Se l'istituto ha più sedi, scegli prima la sede in cui lavorare dal menu **Sede** in alto: ogni sede ha la sua configurazione (e puoi copiarla da un'altra sede quando un'area è vuota).

1. **Sedi** e **Aule**: i plessi dell'istituto e le loro aule (due voci separate del menu). Poi la **Scansione oraria**: gli orari delle ore e le ricreazioni.
2. **Discipline**: le materie, con classe di concorso e aula richiesta.
3. **Quadri orari**: quante ore settimanali di ogni disciplina fa una classe.
4. **Docenti** e **Classi**: le anagrafiche.
5. **Cattedre**: chi insegna cosa, in quale classe e per quante ore.
6. **Vincoli** (facoltativo): regole in più per la generazione. Facoltativi anche i **Laboratori** pomeridiani e le **Impostazioni** (menu del profilo).
7. **Genera orario**: avvia il calcolo automatico. Se non riesce, la pagina elenca i problemi con il pulsante **Correggi**.
8. **Orari**: controlla, correggi a mano ed esporta in PDF.

Perché la generazione possa riuscire, per **ogni classe** devono valere due condizioni:

- le **ore delle cattedre** coincidono con le ore del **quadro orario** della classe;
- il numero di **slot attivi** della classe coincide con le ore del quadro orario (30 ore di quadro = 30 slot attivi).

La **Dashboard** ti dice a che punto sei:

- **Numeri** dell'istituto: classi (a tempo normale e prolungato), docenti per tipo di posto, cattedre con le ore assegnate rispetto a quelle dovute, e le altre anagrafiche.
- **Sei pronto a generare?**: i problemi che bloccherebbero la generazione, ognuno con il link **Correggi** alla pagina giusta. Se non ce ne sono, vedi "Tutto a posto".
- **Generazione e worker**: stato del worker di coda (con **Avvia**), ultima generazione con seed e punteggio, e **Nuova generazione**.
- **Ultimo orario**: versione, stato, punteggio e avvisi aperti, con il tabellone PDF e la ricerca di una classe o di un docente.
- **Carico dei docenti**: chi ha ore assegnate diverse dalle ore dovute; le ore mancanti sono *ore a disposizione*. Accanto al nome compaiono i minuti di **assistenza alle pause** e di **laboratorio** (ore di servizio a parte, che non toccano le ore dovute).
- **Percorso di avvio**: i passi del percorso tipico, spuntati quando hai già inserito qualcosa.

**Eliminare.** Nelle liste si spuntano le righe e si usa *Elimina selezionati*. Se l'eliminazione porta via anche altri dati (per esempio eliminando un docente spariscono le sue cattedre e le sue lezioni negli orari, eliminando una sede tutto ciò che contiene), l'avviso **te lo elenca con i numeri** e ti chiede una **seconda conferma**. Quello che resta ma perde un collegamento (per esempio le classi di un'aula eliminata) è indicato a parte. Non si può annullare.

In alto a destra, sotto l'icona con le tue **iniziali**, c'è il **menu del profilo**: mostra il tuo nome e ruolo, le **Impostazioni** (per chi può consultare), le voci di amministrazione (*Utenze* e *Dati* per l'amministratore, *Registro attività* per amministratore e dirigente scolastico) e **Esci**.

Per ogni pagina trovi anche una breve guida in alto. Le voci del menu e i pulsanti che non vedi dipendono dal tuo ruolo (vedi *Utenze e ruoli*).

**Esporta e importa CSV.** Sedi, aule, scansione oraria, discipline, quadri orari, docenti, classi e cattedre hanno in alto il collegamento **Esporta CSV** (lo scarichi e lo apri in Excel) e, per chi può modificare la lista, **Importa CSV**: servono per caricare molti dati insieme o per portarli da un'installazione all'altra.

- **Come fare:** esporta la lista, aggiungi le righe in Excel e reimporta il file. Il separatore può essere la virgola o il punto e virgola.
- **Esito:** le righe con errori vengono elencate con il numero di riga e le altre sono importate; le righe già presenti vengono **saltate** (l'import non le aggiorna).
- **Ordine:** i riferimenti ad altre liste si scrivono con il nome o il codice (la disciplina di una cattedra, l'aula base di una classe, ...), quindi importa prima le liste da cui dipendono: sedi, aule, scansione oraria, discipline, quadri orari, docenti, classi, cattedre.
- **Scansione oraria:** una riga per ora (colonne `ora`, `inizio`, `fine`, `ricreazione_minuti` e, facoltativa, `nome_pausa`); l'import **sostituisce** orari e ricreazioni di tutte le ore (il file deve averle tutte; se c'è un errore non cambia nulla).
- **Quadri orari:** una riga per disciplina del quadro (con il codice della disciplina); l'import crea solo i quadri nuovi, ciascuno per intero o per niente, e le ore totali si ricalcolano dalle righe.
- **Non si importano:** vincoli, sostegno e le altre impostazioni.

## Sedi e aule
<!-- sezione: consulta -->

### Sedi

In alto, a sinistra dei pulsanti, vedi sempre la **sede** in cui stai lavorando. Se l'istituto ha **più sedi** diventa un menu **Sede**: scegli la sede in cui lavorare. Ogni sede ha i suoi docenti, discipline, quadri orari, scansione oraria, vincoli, laboratori e orari; tutte le pagine mostrano e salvano solo i dati della sede scelta. L'ultima sede usata viene ricordata. Una persona che lavora in due sedi ha una scheda docente per ciascuna. Ogni elemento nasce nella sede in cui stai lavorando; lo stesso codice disciplina, la stessa sezione o la stessa email possono esistere in sedi diverse. **Una sede appena creata è vuota**: nella pagina *Scansione oraria* trovi il pulsante **Crea la scansione standard**, poi inserisci aule, discipline, quadri orari, docenti e classi. L'ultima sede non si può eliminare. Eliminare una sede elimina anche tutto ciò che contiene: l'avviso te lo elenca con i numeri e chiede una seconda conferma.

**Copiare da un'altra sede.** Per non ripartire da zero, nelle pagine *Scansione oraria*, *Discipline*, *Quadri orari*, *Aule* e *Vincoli*, se l'area è **vuota** nella sede in cui lavori e un'altra sede ha dei dati, compare un riquadro azzurro: scegli la sede e premi **Copia**. Si copia solo su un'area vuota. Cosa viene copiato: la scansione oraria con le impostazioni (durata dell'ora, sostegno), le discipline (con le discipline padre), i quadri orari, le aule (con piano e capienza) e i vincoli **per tutte le classi e i docenti**. L'ordine conta: i **quadri** richiedono le stesse discipline (copiale prima); i **vincoli** su una disciplina o su certe ore richiedono le stesse discipline e la stessa scansione, altrimenti vengono saltati e ti viene detto quali. Docenti, classi, cattedre e vincoli di classi o docenti specifici non si copiano. Ogni copia è un solo rigo nel registro attività.

Le **sedi** sono i plessi: se l'istituto ne ha uno solo, basta una sede.

- **Nome**: il nome del plesso.
- **Indirizzo**: facoltativo, solo informativo.

### Aule

Ogni aula appartiene alla sede in cui stai lavorando (non si sceglie nel form).

- **Nome**: es. "Aula 12" o "Palestra".
- **Tipo**: la categoria dell'aula (classe, laboratorio, palestra, aula di musica, ...). Il tipo serve a collegare le discipline che richiedono un'aula speciale: una lezione di Scienze motorie, se la disciplina richiede il tipo "palestra", si svolge in un'aula di quel tipo.
- **Piano**: il piano dell'edificio (0 = piano terra, negativo = interrato). Facoltativo: serve al vincolo *Spostamenti tra piani* (C5).
- **Capienza**: quante lezioni possono svolgersi **nello stesso momento** in aule di quel tipo. 1 = una sola classe alla volta; 2 = due classi insieme (es. palestra divisibile). Non è il numero di alunni.

La colonna **Usata da** mostra quali discipline richiedono quel tipo di aula.

<!-- permesso: gestisci-anagrafica -->
### Didattica DADA

Con la didattica DADA le classi non hanno un'aula fissa: **sono gli alunni a spostarsi** nell'aula della disciplina.

1. Vai in **Aule** e crea un'aula.
2. Come tipo scegli **«DADA»** e **spunta le discipline** che si svolgono in quell'aula (una sola per un'aula dedicata, più d'una per un'aula condivisa).
3. Le discipline spuntate vengono collegate automaticamente all'aula. Le **seconde lingue** (francese, spagnolo, tedesco…) condividono un unico tipo, **«DADA · Seconda lingua»**: crea più aule di quel tipo se servono e scegli una qualunque delle lingue.
4. Nelle classi lascia vuoto il campo **Aula base**.
5. Ogni disciplina DADA può avere **più aule**: due aule di Italiano, oppure la **propria aula più una condivisa** con altre discipline (crei l'aula di Italiano, quella di Inglese, quella di Spagnolo e poi un'aula «DADA» con le tre spuntate). Le aule si sommano: il generatore sceglie quella libera a ogni ora. Nella scheda della disciplina, **Altre aule ammesse** mostra e permette di correggere l'elenco.
6. Per ridurre i **salti di piano** degli alunni tra un'ora e l'altra, indica il **piano** di ogni aula e aggiungi il vincolo **Spostamenti tra piani (C5)** da *Vincoli* (vedi gli esempi lì).
7. Per leggere l'orario ci sono tre modi, tutti nella pagina **Orari**: la **vista classe** (dove deve andare la classe a ogni ora, con il nome dell'aula in ogni lezione), la **vista aula** (chi arriva in aula a ogni ora: è anche il foglio da appendere alla porta) e la **vista docente**. Con la didattica tradizionale restano utili le stesse viste, ma nella classe l'aula compare solo quando non è la sua (palestra, laboratori).
<!-- /permesso -->


## Scansione oraria
<!-- sezione: consulta -->

La scansione oraria definisce **a che ora inizia e finisce ogni ora di lezione** e dove sono le **ricreazioni**. È unica per tutta la scuola e uguale per tutti i giorni.

- **Ora**: 1ª–9ª (le ore 7ª–9ª sono quelle del pomeriggio).
- **Inizio** e **Fine**: gli orari dell'ora. Le ore non possono sovrapporsi e ognuna deve finire dopo il suo inizio. La **durata** si calcola da sola.
- **Ricreazione dopo (minuti)**: per le ore seguite da una ricreazione scrivi **quanti minuti dura**; lascia vuoto dove non ce n'è. Puoi averne quante ne servono, ciascuna di durata diversa (per esempio 10 minuti dopo la 3ª ora e 15 dopo la 5ª). La ricreazione **parte dalla fine di quell'ora** e accanto compare l'orario calcolato (es. 10:30–10:40). L'ora successiva deve iniziare **non prima** della fine della ricreazione: se tra le due ore resta altro tempo libero, quello non è una ricreazione e nei PDF non compare. Svuotando il campo la ricreazione si toglie. **Mentre scrivi i minuti, l'inizio dell'ora successiva si sposta da solo** (e con lui le ore che la seguono nella stessa mattinata): lo stesso succede se cambi la **Fine** di un'ora. Le ore del pomeriggio, separate da una pausa lasciata a mano, non si toccano; puoi sempre ritoccare gli orari dopo lo spostamento.
- **Pausa prima della prima ora**: la prima riga della tabella, «Prima della 1ª», permette di aggiungere una pausa **prima** della prima ora (per esempio un'accoglienza): scrivi i **minuti** e, se vuoi, il nome. La pausa **finisce quando comincia la prima ora** (con la prima ora alle 8:00 e 10 minuti, va dalle 7:50 alle 8:00) e l'orario si calcola da solo. Vale per tutti i giorni; compare nei PDF (riga sopra la prima ora e legenda del tabellone) e si può assegnare un docente per l'assistenza, come per le altre pause.
- **Nome della pausa**: facoltativo, accanto ai minuti. Scrivi per esempio **Mensa** per la pausa lunga prima dei rientri pomeridiani; se resta vuoto la pausa si chiama «Ricreazione». Il nome compare nei PDF al posto di «Ricreazione». Hanno la mensa le classi che hanno ore dopo quella pausa (i rientri pomeridiani).

<!-- permesso: gestisci-anagrafica -->
Modifica gli orari e salva una volta sola con il pulsante in basso a destra. Un errore ti dice quale ora non torna (per esempio un'ora che inizia prima della fine della ricreazione che la precede).
<!-- /permesso -->

Con **Esporta CSV** (in alto) scarichi la scansione in un file e con **Importa CSV** la carichi da un file, per esempio su un'altra installazione; vedi *Esporta e importa CSV* in *Per iniziare*.

Gli orari delle ore e le ricreazioni compaiono nei **PDF**: nelle griglie di classe e di docente come orario accanto a ogni ora e come riga «Ricreazione 10:30-10:40 (10 minuti)» (o con il nome che hai dato alla pausa, per esempio «Mensa») al posto giusto; nel tabellone generale in una riga di legenda in fondo.

## Discipline
<!-- sezione: consulta -->

Il catalogo delle materie insegnate.

- **Codice**: la sigla breve e unica (es. ITA, MAT). È quella che compare nel tabellone PDF. Per una **seconda lingua straniera** usa FRA, SPA o TED (oppure scrivi «seconda lingua» nel nome): sono i codici con cui l'aula DADA delle lingue viene riconosciuta.
- **Nome**: es. Italiano, Matematica.
- **Classe di concorso**: il codice di abilitazione dei docenti che la insegnano (es. A022). Serve a proporre le classi di concorso nella scheda del docente; è un dato informativo.
- **Aula richiesta**: l'elenco contiene i tipi di aula già censiti. "Aula della classe" significa nessuna aula speciale: la lezione si svolge dove sta la classe. Se scegli un tipo (es. palestra), le lezioni di quella disciplina occupano un'aula di quel tipo, nei limiti della sua capienza.
- **Altre aule ammesse**: facoltativo, tipi di aula in più in cui la disciplina può svolgersi (per esempio l'aula DADA condivisa con altre discipline). Il generatore sceglie l'aula libera tra tutti i tipi indicati. Di solito si compila da solo quando spunti la disciplina in un'aula DADA.
- **Sotto-disciplina di**: collega materie insegnate dallo stesso docente (es. Storia e Geografia sotto Italiano). È solo informativo.

## Quadri orari
<!-- sezione: consulta -->

Un quadro orario è il monte ore settimanale per disciplina (es. "Tempo normale 30h"). Ogni classe ne usa uno.

- **Nome**: es. "Tempo normale 30h" o "Tempo prolungato 36h".
- **Discipline**: ogni riga è una disciplina con le sue **ore settimanali** (1–40). Una disciplina può comparire una sola volta: quelle già inserite sono disattivate nelle altre righe.
- Il **totale** si aggiorna mentre scrivi.
<!-- permesso: gestisci-anagrafica -->
- Aggiungi e togli le righe con i pulsanti della sezione e salva una volta sola, anche alla creazione.
- Un quadro usato da qualche classe **non si può eliminare**.
<!-- /permesso -->
- **Esporta CSV** e **Importa CSV** (in alto): una riga per disciplina del quadro; importa prima le discipline. Vedi *Esporta e importa CSV* in *Per iniziare*.


## Docenti
<!-- sezione: consulta -->

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
- **Ore dovute**: le ore settimanali di lezione previste dal contratto (1–24). Nell'elenco dei docenti la colonna **Assegnate / dovute** le confronta con le ore già assegnate (cattedre e sostegno), in giallo quando non coincidono. 18 = cattedra intera; un part-time al 50% ne ha 9. La differenza tra ore dovute e ore assegnate nelle cattedre sono le *ore a disposizione*.
- **COE** (Cattedra Orario Esterna): il docente ha ore anche in un'altra scuola. Per lui l'applicazione gestisce solo le **indisponibilità**: segnala tu gli slot in cui è impegnato nell'altra scuola.
- **Classi di concorso abilitanti**: i codici di abilitazione (es. A022 Lettere, A028 Matematica e scienze, AB25 Inglese). L'elenco è preso dalle discipline e accanto a ogni codice vedi le materie che abilita a insegnare (es. *A022 — Geografia, Italiano, Storia*): una sola spunta vale per tutte.

Importante: **contratto, regime, COE e classi di concorso sono dati anagrafici**: il generatore non li usa da soli. Per far rispettare un part-time o un impegno in un'altra scuola devi segnare le **indisponibilità**.

<!-- permesso: gestisci-docenti-classi -->
Nella pagina del docente puoi anche:

- impostare le **indisponibilità**: la griglia degli slot in cui il docente non può avere lezione (part-time, servizio in altre scuole, permessi). Il generatore e l'editor le rispettano sempre;
- registrare le **sospensioni e assenze lunghe** (sospensione dal servizio, malattia lunga, congedo o aspettativa): per ognuna scrivi **dal** e, se la conosci, **al** (vuoto = fino a nuova comunicazione), il motivo e una nota. Nell'elenco dei docenti chi è sospeso oggi ha l'etichetta **Sospeso**;
- gestire le **cattedre**: aggiungi o togli righe e guarda il totale "assegnate / dovute", che diventa ambra se non coincide.

**Come funziona la sospensione.** Spuntando **Escludi dall'orario** (è già spuntato di norma), finché la sospensione è in corso il docente **non può avere cattedre**: i controlli prima di generare lo segnalano con un link alla sua scheda e **la generazione non parte** finché non riassegni le sue cattedre a un **supplente** (un altro docente, per esempio con contratto *Supplenza breve*) dalla pagina **Cattedre** o dalla scheda del docente, oppure non chiudi la sospensione. Togli la spunta per un'assenza **breve** che non deve cambiare l'orario base: in quel caso viene solo registrata. Sospensioni già finite o non ancora iniziate non bloccano nulla. La proposta automatica dei sostituti per i giorni di assenza arriverà con la gestione di assenze e sostituzioni.

**Assistenza alle pause (mensa).** Sotto le sospensioni, la sezione **Assistenza alle pause** indica quando il docente **sorveglia gli alunni** durante una pausa (anche quella prima della prima ora), per esempio la mensa: per ogni riga scegli il **giorno** e la **pausa** (tra quelle definite in *Scansione oraria*, con il loro nome e orario). Vale per tutti gli orari: non dipende da una generazione e non cambia con le versioni. Compare sotto l'orario del docente, nei PDF del docente («Assistenza alle pause») e nel **carico dei docenti** della dashboard (minuti di assistenza, ore di servizio a parte: non toccano le ore dovute di cattedra). Un avviso segnala se il docente è indisponibile tutto quel giorno. La modificano amministratore e referente orario.

**Supplenti.** Nella riga della sospensione puoi indicare **uno o più supplenti** spuntandoli nell'elenco dei docenti (scorri l'elenco se è lungo). Dopo aver salvato, **Gestisci sostituzione** apre la pagina dove le cattedre del titolare passano ai supplenti con un clic: per ogni cattedra scegli il supplente (con un solo supplente passano tutte insieme). Le lezioni dell'orario restano le stesse e le eredita il supplente. I controlli prima di generare ti rimandano a quella pagina finché le cattedre non sono state passate. Quando il titolare rientra, **Riporta al titolare** rimette a lui le cattedre; se la sospensione è finita e le cattedre sono ancora ai supplenti, i controlli prima di generare te lo ricordano. Togliendo la sospensione dalla scheda le cattedre tornano al titolare da sole. La sostituzione la gestiscono l'amministratore e il referente orario.

Salva con il pulsante **Salva** in basso a destra: un solo salvataggio vale per tutta la pagina. Per importare più docenti insieme vedi **Esporta e importa CSV** in *Per iniziare*.
<!-- /permesso -->


## Classi
<!-- sezione: consulta -->

Ogni classe ha:

- **Anno di corso**: 1ª, 2ª o 3ª.
- **Sezione**: la lettera (A, B, C, ...). Anno e sezione insieme devono essere unici nella stessa sede. La classe nasce nella sede in cui stai lavorando.
- **Aula base**: l'aula in cui la classe fa le lezioni che non richiedono un'aula speciale. Lasciala vuota con la didattica DADA.
- **Piano della classe**: dove sta la classe (0 = piano terra). Facoltativo: conta, nel vincolo *Spostamenti tra piani* (C5), per le ore che si svolgono senza un'aula assegnata.
- **Quadro orario**: il monte ore settimanali per disciplina.
- **Tempo scuola**:
  - *Normale*: 30 ore settimanali, solo la mattina.
  - *Prolungato*: più ore (36, fino a 40) con **rientri pomeridiani**.
- **Numero alunni** (0–35): dato informativo. Gli alunni non sono censiti uno per uno e il numero non cambia il calcolo.

### Rientri pomeridiani

Con il **tempo prolungato** puoi scegliere i **giorni di rientro**, ognuno in modo indipendente (per esempio martedì e giovedì per una classe, lunedì e mercoledì per un'altra). Spuntando un giorno si attivano le sue ore pomeridiane (7ª–9ª) nella griglia degli slot attivi. Il campo è attivo solo con Tempo scuola = Prolungato.

### Slot attivi

La griglia degli **slot attivi** indica le ore della settimana che la classe usa davvero: le righe sono le ore (1ª–9ª), le colonne i giorni. Il generatore riempie esattamente quegli slot, né di più né di meno, quindi **il numero di slot attivi deve coincidere con le ore del quadro orario**. Puoi modificare le singole ore a mano, per casi particolari: in modifica la griglia prevale sui giorni di rientro.

<!-- permesso: gestisci-anagrafica -->
### Cattedre

Nella pagina della classe aggiungi o togli le cattedre (disciplina, docente, ore, compresenza). Il totale mostra le ore assegnate rispetto al quadro orario.
<!-- /permesso -->

### Sostegno

Gli alunni con sostegno non sono censiti: ogni **fabbisogno** ha solo un codice anonimo (es. `1B-S1`) e le ore settimanali. Poi assegni i **docenti di sostegno** con le loro ore.

- **Codice anonimo**: un'etichetta che identifica un alunno senza nome (es. classe + progressivo). Deve essere unico nella classe.
- **Ore/sett.** del fabbisogno: le ore di sostegno di cui l'alunno ha bisogno.
- **Docente unico**: il fabbisogno deve essere coperto da un solo docente.
- **Conteggio ore**: "per alunno" somma le ore di tutti i fabbisogni (ogni compresenza vale per un alunno); "per classe" prende il fabbisogno più alto (una compresenza copre tutta la classe). Puoi usare il valore predefinito dell'istituto.
- **Docenti assegnati**: ogni docente con le sue ore. L'elenco contiene solo chi ha tipo posto **Sostegno**; lo stesso docente non si può assegnare due volte alla stessa classe.
- Il generatore programma le **compresenze** di sostegno. Il totale "assegnate / richieste" ti dice se le ore bastano.

## Cattedre
<!-- sezione: consulta -->

Una cattedra assegna un docente a una disciplina per una classe. La pagina **Cattedre** è l'elenco completo, filtrabile per classe e per docente. Puoi anche modificarle dalle pagine di docente e classe.

- **Classe**, **Disciplina**, **Docente**: chi insegna cosa e dove.
- **Ore settimanali** (1–20): quante ore di quella disciplina il docente fa in quella classe.
- **Stato** (nell'elenco): un'etichetta colorata confronta le ore delle cattedre della classe in quella disciplina con quelle del quadro orario: **OK** (verde), **Mancano N h** (giallo), **N h in più** o **Non nel quadro** (rosso). Le cattedre in compresenza non contano nel confronto e hanno l'etichetta grigia *Compresenza*. Passando il mouse sull'etichetta vedi il dettaglio.
- **Compresenza**: segnala che la cattedra è svolta insieme a un altro docente nella stessa ora. Per ora è un'indicazione: **non conta nell'etichetta Stato**, ma il generatore e i controlli prima di generare contano comunque le sue ore (quindi, se le inserisci, il totale della classe può superare il quadro orario). Le compresenze di sostegno si gestiscono invece dalla scheda della classe.

La stessa combinazione docente + classe + disciplina può comparire una sola volta. La somma delle cattedre di una classe deve coincidere col suo quadro orario; quella di un docente non dovrebbe superare le sue ore dovute.

## Laboratori
<!-- sezione: consulta -->

I **laboratori pomeridiani** (per esempio Latino) sono attività **fuori dal monte ore e dalla generazione**: li assegni tu a mano. L'orario però ne tiene conto, perché un docente in laboratorio non può fare lezione e un'aula occupata da un laboratorio non è libera.

- **Nome**: es. «Latino».
- **Aula**: facoltativa. Se indicata, il laboratorio la occupa nelle ore scelte.
- **Docenti**: uno o più; sono occupati nelle ore scelte.
- **Quando**: le ore del **pomeriggio** (7ª–9ª) di uno o più giorni, in una griglia con i **giorni in riga** e le **ore in colonna** (gli orari sono nell'intestazione; clicca nel riquadro per scegliere l'ora). Quando hai scelto docenti e aula, le ore **non libere** si colorano di **giallo**: passando il mouse vedi il motivo (il docente ha lezione in quell'ora, è indisponibile, è già in un altro laboratorio, l'aula è occupata). Il confronto usa l'orario **pubblicato**, o l'ultimo se non ce n'è uno pubblicato.
- **Classi destinatarie**, **partecipanti** e **note**: facoltativi, solo informativi.
- **Attivo**: se tolto, il laboratorio resta salvato ma non occupa nessuno.

<!-- permesso: gestisci-anagrafica -->
Crea, modifica ed elimina i laboratori dall'elenco, come le altre anagrafiche. I laboratori sono della sede in cui stai lavorando. Amministratore e referente orario.
<!-- /permesso -->

**Effetti**
- **Generazione:** il generatore non mette lezioni a un docente, né usa un'aula, nelle ore in cui hanno un laboratorio.
- **Controllo dell'orario:** se un laboratorio sovrappone una lezione (di un docente o dell'aula), o un docente è in due laboratori insieme o indisponibile, compare un errore nel Controllo, come per le lezioni. Non blocca nulla: lo risolvi spostando il laboratorio o la lezione. Gli stessi conflitti sono in cima all'elenco dei laboratori.
- **Dove si vedono:** sotto l'orario del docente e dell'aula, e nei loro PDF («Laboratori pomeridiani»).
- **Ore dei docenti:** i minuti di laboratorio compaiono nel carico dei docenti della dashboard, come ore di servizio a parte (non toccano le ore dovute).

## Impostazioni
<!-- sezione: consulta -->

Si apre dal **menu del profilo** (le tue iniziali, in alto a destra). Le impostazioni valgono per la **sede in cui stai lavorando**: ogni sede ha le sue. Per ora ce n'è una sola.

- **Conteggio predefinito delle ore di sostegno**: *per alunno* (ogni ora di un docente di sostegno vale per un solo alunno) oppure *per classe* (un docente di sostegno segue più alunni della classe insieme e l'ora vale per ciascuno). Vale per tutte le classi che non scelgono un conteggio proprio: nella scheda di una classe, sezione *Sostegno*, il campo **Conteggio ore** mostra «Default della sede» e il valore attuale.

<!-- permesso: gestisci-anagrafica -->
Modifica e salva con il pulsante in basso a destra. Amministratore e referente orario; gli altri la vedono in sola lettura. Con più sedi, cambia sede dal menu in alto per modificare le impostazioni di un'altra.
<!-- /permesso -->

La durata di ogni ora e i giorni della settimana non sono impostazioni: si ricavano dalla **scansione oraria**.

## Vincoli
<!-- sezione: consulta -->

I vincoli sono regole aggiuntive per la generazione, oltre a quelle di sistema sempre attive (un docente non può essere in due posti insieme, una classe ha una sola lezione per slot, le indisponibilità sono rispettate, ecc.).

- **Tipo**: quale regola applicare (vedi sotto). I campi mostrati cambiano col tipo.
- **Ambito**: a chi si applica. *Globale* = tutte le classi/tutti i docenti; *Classe* o *Docente* = solo quelli scelti. Le classi si scelgono solo con Ambito = Classe, i docenti solo con Ambito = Docente. Il vincolo più specifico prevale su quello globale. Non tutti gli ambiti valgono per ogni tipo: **D1, D3 e D6** si applicano a *Globale* o *Classe*; **T2 e T3** a *Globale* o *Docente*.
- **Severità**: *Rigido* va rispettato sempre (se non è possibile l'orario risulta infattibile); *Preferenziale* viene rispettato quando possibile.
- **Peso** (1–100): solo per i preferenziali. Più è alto, più il generatore cerca di evitare di violarlo. Si attiva solo con Severità = Preferenziale.
- **Attivo**: se tolto, il vincolo resta salvato ma non viene considerato.
- **Nota**: promemoria libero.

Nell'elenco dei vincoli la colonna **Disciplina** mostra la disciplina a cui si riferisce (D1, D3, D6), «Tutte» se il vincolo vale per ogni disciplina (D1 con Ambito Docente) e «—» per i tipi che non ne hanno.

Tipi disponibili:

- **Blocco consecutivo minimo (D1)**: una disciplina (o, con Ambito *Docente*, le lezioni dei docenti scelti) in blocchi di almeno N ore consecutive. Campi: disciplina (facoltativa **solo con Ambito Docente**: lasciando «Qualsiasi disciplina» contano tutte le lezioni del docente, in qualunque classe e materia), *min ore consecutive* (2–6), *n. blocchi minimi* (1–5): in quanti **giorni diversi** della settimana deve esserci almeno un blocco.
- **Max ore/giorno per disciplina (D3)**: limite di ore al giorno della stessa disciplina. Campi: disciplina, *max ore/giorno*.
- **Fascia oraria vietata/preferita (D6)**: ore in cui una disciplina non va (o è preferibile) collocata. Campi: disciplina, *tipo fascia* (vietata/preferita), gli *slot* interessati, indicati con giorno e ora (LUN-1ª, MAR-3ª, ...).
- **Giorno libero (T2)**: un docente ha uno o più giorni liberi. Campi: *n. giorni liberi richiesti* (1–3), *giorno preferito* facoltativo.
- **Spostamenti tra piani (C5)**: riduce i cambi di piano di una classe tra due ore consecutive (utile con la didattica DADA, dove sono gli alunni a spostarsi). Ambito *globale* o *classe*. Campo: *piani di differenza senza penalità* (0 = ogni cambio di piano pesa). Il piano di una lezione è quello dell'aula in cui si svolge, o della classe se non ha un'aula; le aule e le classi **senza piano** non contano.
- **Max ore buche (T3)**: limite alle *buche* (ore vuote tra due lezioni dello stesso docente nello stesso giorno). Campi: *max buche/giorno*, *max buche/settimana*.

<!-- permesso: gestisci-anagrafica -->
### Esempi di utilizzo

Ogni esempio è una tabella con i campi del form **Nuovo vincolo** e il valore da inserire. I campi non elencati si lasciano come sono. I pesi sono indicativi: 1–30 = desiderio lieve, 40–70 = importante, 80–100 = quasi obbligatorio.

**Blocco consecutivo minimo (D1)**

*Arte in due ore di fila, per il laboratorio.*

| Campo | Valore |
| --- | --- |
| Tipo | Blocco consecutivo minimo (D1) |
| Ambito | Globale |
| Disciplina | Arte e immagine |
| Min ore consecutive | 2 |
| N. blocchi minimi | 1 |
| Severità | Preferenziale |
| Peso | 50 |

In ogni classe il generatore cerca di avere almeno un giorno con due ore di Arte consecutive; se non ci riesce paga una penalità.

*Scienze sempre in due ore per una sola classe.*

| Campo | Valore |
| --- | --- |
| Tipo | Blocco consecutivo minimo (D1) |
| Ambito | Classe |
| Classi | 2ª B |
| Disciplina | Scienze |
| Min ore consecutive | 2 |
| N. blocchi minimi | 1 |
| Severità | Rigido |

Per la 2ª B le Scienze devono avere almeno un blocco da due ore; se è impossibile la generazione risulta infattibile.

*Alcuni docenti con almeno un blocco di due ore di fila, di qualunque materia.*

| Campo | Valore |
| --- | --- |
| Tipo | Blocco consecutivo minimo (D1) |
| Ambito | Docente |
| Docenti | i docenti interessati (anche più d'uno) |
| Disciplina | Qualsiasi disciplina |
| Min ore consecutive | 2 |
| N. blocchi minimi | 1 |
| Severità | Rigido |

Per ciascun docente scelto, in almeno un giorno della settimana ci sono due sue lezioni consecutive, anche in classi o materie diverse. Se indichi una disciplina contano solo le sue lezioni di quella disciplina.

**Max ore/giorno per disciplina (D3)**

*Scienze motorie al massimo un'ora al giorno.*

| Campo | Valore |
| --- | --- |
| Tipo | Max ore/giorno per disciplina (D3) |
| Ambito | Globale |
| Disciplina | Scienze motorie |
| Max ore/giorno | 1 |
| Severità | Rigido |

In nessuna classe ci saranno due ore di motoria lo stesso giorno.

*Italiano non più di due ore al giorno nella 1ª A.*

| Campo | Valore |
| --- | --- |
| Tipo | Max ore/giorno per disciplina (D3) |
| Ambito | Classe |
| Classi | 1ª A |
| Disciplina | Italiano |
| Max ore/giorno | 2 |
| Severità | Preferenziale |
| Peso | 70 |


**Fascia oraria vietata o preferita (D6)**

*Matematica mai all'ultima ora.*

| Campo | Valore |
| --- | --- |
| Tipo | Fascia oraria vietata/preferita (D6) |
| Ambito | Globale |
| Disciplina | Matematica |
| Tipo fascia | Vietata |
| Slot | 6ª ora di tutti i giorni |
| Severità | Preferenziale |
| Peso | 40 |

Ogni lezione di Matematica messa alla 6ª ora aggiunge una penalità.

*Matematica preferibilmente nelle prime tre ore.*

| Campo | Valore |
| --- | --- |
| Tipo | Fascia oraria vietata/preferita (D6) |
| Ambito | Globale |
| Disciplina | Matematica |
| Tipo fascia | Preferita |
| Slot | 1ª, 2ª e 3ª ora di tutti i giorni |
| Severità | Preferenziale |
| Peso | 30 |

Ogni lezione fuori da quelle ore aggiunge una penalità. Con severità *Rigido* la disciplina potrebbe stare **solo** in quegli slot.

**Giorno libero (T2)**

*Ogni docente ha almeno un giorno senza lezioni, meglio il venerdì.*

| Campo | Valore |
| --- | --- |
| Tipo | Giorno libero (T2) |
| Ambito | Globale |
| N. giorni liberi richiesti | 1 |
| Giorno preferito (opzionale) | Venerdì |
| Severità | Preferenziale |
| Peso | 20 |

Il giorno preferito è solo un piccolo bonus.

*Un docente deve avere due giorni liberi, a scelta del generatore.*

| Campo | Valore |
| --- | --- |
| Tipo | Giorno libero (T2) |
| Ambito | Docente |
| Docenti | il docente |
| N. giorni liberi richiesti | 2 |
| Severità | Rigido |

Se i giorni liberi sono già decisi (per esempio un part-time verticale con giorni fissi) è meglio segnare le **indisponibilità** del docente: T2 lascia scegliere i giorni al generatore.

**Max ore buche (T3)**

Una *buca* è un'ora vuota tra due lezioni dello stesso docente nello stesso giorno.

*Al massimo una buca al giorno per tutti.*

| Campo | Valore |
| --- | --- |
| Tipo | Max ore buche (T3) |
| Ambito | Globale |
| Max buche/giorno | 1 |
| Severità | Preferenziale |
| Peso | 60 |


*Una docente con al massimo tre buche a settimana.*

| Campo | Valore |
| --- | --- |
| Tipo | Max ore buche (T3) |
| Ambito | Docente |
| Docenti | la docente |
| Max buche/settimana | 3 |
| Severità | Rigido |

Basta compilare uno solo dei due limiti (giorno o settimana).

**Spostamenti tra piani (C5)**

Serve a evitare, per esempio, che gli alunni vadano dal piano 1 al piano 3 e poi tornino al piano 1 in tre ore consecutive. Indica prima il **piano** delle aule (e, se serve, delle classi).

*Meno spostamenti possibile per tutte le classi.*

| Campo | Valore |
| --- | --- |
| Tipo | Spostamenti tra piani (C5) |
| Ambito | Globale |
| Piani di differenza senza penalità | 0 |
| Severità | Preferenziale |
| Peso | 30 |

Il generatore sceglie gli orari in modo da ridurre la somma dei piani percorsi.

*Mai più di un piano di differenza tra due ore consecutive.*

| Campo | Valore |
| --- | --- |
| Tipo | Spostamenti tra piani (C5) |
| Ambito | Classe |
| Classi | le classi interessate |
| Piani di differenza senza penalità | 1 |
| Severità | Rigido |

Può rendere impossibile l'orario: se succede, ammorbidiscilo o rendilo preferenziale.

Conta solo il passaggio tra **ore consecutive** dello stesso giorno; un'ora vuota in mezzo dà il tempo di spostarsi.

**Consigli**

- Parti con vincoli **preferenziali**: usa il **Rigido** solo per ciò che non si può mai violare. Troppi vincoli rigidi, o in contrasto tra loro, rendono l'orario infattibile.
- Se un vincolo ti crea problemi, togli la spunta **Attivo** invece di eliminarlo: resta salvato e puoi riattivarlo.
- Dopo aver aggiunto o cambiato dei vincoli, rigenera l'orario: quelli già generati non cambiano.
<!-- /permesso -->


## Genera orario
<!-- sezione: consulta -->

Con più sedi, ogni sede ha le sue generazioni e i suoi orari: si genera, si approva e si pubblica **una sede alla volta**, nella sede in cui stai lavorando. Le versioni dell'orario sono numerate per sede e l'orario pubblicato di una sede non cambia quando pubblichi quello di un'altra. I PDF riportano il nome della sede nel titolo.


La generazione avviene **in background**: avvia il calcolo e segui l'avanzamento nella pagina.

<!-- permesso: gestisci-anagrafica -->
1. Controlla in alto lo stato del **worker di coda**, il programma che esegue i calcoli. Se è "fermo", premi **Avvia**: senza worker le generazioni restano in coda. Quando premi **Avvia generazione** il worker, se è fermo, parte da solo (compare un messaggio); se non riesce a partire vedi il motivo. Per fermarlo usa **Ferma**: termina prima il job in corso. Se lo vedi "in arresto" puoi già riavviarlo.
2. Premi **Nuova generazione** e compila:
   - **Tempo limite** (10–900 secondi): per quanto tempo il generatore può cercare un orario migliore. Più tempo, orari generalmente migliori.
   - **Nome dell'orario** (facoltativo): per riconoscerlo poi nell'elenco (es. «Orario di base»). Senza nome si chiamerà «Orario v1», «Orario v2», …
   - **Seed**: si sceglie da un elenco. **Casuale** produce un orario diverso a ogni generazione; scegliendo un seed già usato (elencato con il nome dell'orario che ha prodotto) e a dati invariati si **riottiene lo stesso orario**. Il seed usato viene sempre registrato.
<!-- /permesso -->

Lo **stato** di una generazione:
   - *In coda*: aspetta che il worker la prenda in carico (se non parte, il worker è fermo).
   - *In corso*: il calcolo è in esecuzione; la barra mostra l'avanzamento.
   - *Completata*: l'orario è pronto e compare tra gli **Orari**.
   - *Infattibile*: non esiste un orario valido con i dati e i vincoli attuali (o il tempo è finito senza trovarne uno). La pagina elenca **quali vincoli o risorse sono in conflitto**, e accanto a ogni problema che si sa dove correggere c'è il pulsante **Correggi**, che apre la pagina giusta (la classe, il docente, le aule, i vincoli, come nei controlli della dashboard). Corretti i problemi, usa **genera di nuovo**.
   - *Fallita*: errore tecnico durante il calcolo. Premi **Scarica diagnostica** nella pagina della generazione e invia il file a chi gestisce l'applicazione: contiene l'errore, i vincoli attivi, i controlli sui dati e il log.

Il **punteggio** di un orario somma le penalità dei vincoli preferenziali violati: **0 = tutti rispettati, più è basso meglio è**.

Prima del calcolo l'applicazione controlla i dati: ore delle cattedre contro quadro orario, slot attivi contro quadro orario, ore dei docenti contro slot disponibili, capacità delle aule e ore di sostegno. I problemi trovati vengono mostrati senza avviare il calcolo.

## Orari e modifica manuale
<!-- sezione: consulta -->

La pagina **Orari** elenca gli orari prodotti, con **periodo**, **versione**, **stato** e **punteggio**. Ogni orario nasce in *bozza*.

### Stato di un orario

Un orario segue questo percorso: **Bozza → In revisione → Approvato → Pubblicato → Archiviato**.

- **Bozza**: l'unico stato in cui si può modificare la griglia (trascinare, scambiare, bloccare, cambiare docente o materia). Chi gestisce l'anagrafica la prepara e usa **Invia in revisione**.
- **In revisione**: sola lettura. Il dirigente può **Approvare** oppure rimandare in bozza con **Riporta in bozza**.
- **Approvato**: sola lettura. Chi approva può **Pubblicare** l'orario o riportarlo in bozza.
- **Pubblicato**: è l'orario in vigore. Per ogni periodo ce n'è **uno solo**: pubblicandone un altro, il precedente passa da solo in **Archiviato**.
- **Archiviato**: resta consultabile ed esportabile.

Ogni orario è una **scheda** con tre blocchi: **Consulta** (le viste per classe e per docente), **Esporta in PDF** e **Stato e copia**. I pulsanti dello stato compaiono nella scheda dell'orario solo per i passaggi che il tuo ruolo può fare: **approvare, pubblicare e archiviare** spetta all'amministratore e al dirigente scolastico; **inviare in revisione** a chi gestisce l'anagrafica. Ogni cambio di stato resta nel registro delle modifiche.

### Duplicare un orario

**Duplica** (per chi gestisce l'anagrafica) crea una **copia in bozza** di qualunque orario, qualunque sia il suo stato: stesse lezioni, stesse compresenze di sostegno e stesso periodo, versione successiva. Ti chiede il **nome della copia** (proposto: «nome dell'originale (copia)»). Gli avvisi non si copiano. L'originale non cambia.

**Cambi temporanei dell'orario** (una settimana con l'uscita didattica, un docente assente per qualche giorno, …): non modificare l'orario in vigore. **Duplicalo** dandogli un nome parlante (es. «Settimana 6–10 ottobre – uscita didattica»), apporta i cambi sulla copia in bozza, poi falla passare per revisione, approvazione e pubblicazione quando serve; l'orario di base resta intatto e tornerai a usarlo alla fine. Ricorda che pubblicando una versione la precedente dello stesso periodo passa in archivio: per tornare all'orario di base, **duplica quello archiviato** e ripubblicalo.

**Rinomina** (accanto al nome, nella scheda) cambia il nome in qualsiasi momento.

Da questa pagina puoi inoltre:

- aprire la griglia di una classe (modificabile), di un docente o di un'**aula** (sola lettura), con le **select di ricerca**: scrivi parte del nome per trovare la voce. Nella griglia di classe e di docente, sotto ogni lezione compare l'**aula** quando è diversa dall'aula base della classe (sempre, con la didattica DADA). La **vista aula** mostra, ora per ora, quale classe e quale docente ci sono (anche in aule con più classi insieme, come la palestra) e quando l'aula è **libera**; comprende le lezioni delle classi che hanno quell'aula come aula base;
- scaricare il **tabellone generale in PDF**;
<!-- permesso: gestisci-anagrafica -->
- eliminare un orario selezionandolo con la casella in alto a sinistra della sua scheda (o con **Seleziona tutti**): si eliminano solo gli orari in **bozza** o **archiviati** (la generazione resta nello storico).
<!-- /permesso -->

<!-- permesso: gestisci-anagrafica -->
### Griglia della classe

- La griglia si modifica solo se l'orario è in **bozza**: negli altri stati compare un avviso azzurro e la griglia è in sola lettura.
- **Trascina** una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si **scambiano**. Gli slot si colorano durante il trascinamento per dirti dove si può (verde), dove creerebbe un conflitto (ambra) e dove non è ammesso (rosso).
- Il menu dentro la lezione cambia **materia e/o docente** (cerca per materia o per docente).
- **Blocca/Sblocca**: una lezione bloccata non si sposta, non si scambia e non si modifica.
- **Annulla** e **Ripeti** (in alto sopra la griglia) tornano indietro e avanti **su più livelli**: spostamenti, scambi, cambi di docente o materia e blocchi/sblocchi. Scorciatoie: **Ctrl/Cmd + Z** per annullare, **Ctrl/Cmd + Maiusc + Z** (o Ctrl + Y) per ripetere. I pulsanti sono grigi quando non c'è nulla da annullare o da ripetere. Dopo una **nuova** modifica le modifiche annullate non si possono più ripetere. Annullamenti e ripristini restano nel registro delle modifiche. La cronologia riguarda le modifiche fatte da questa versione in poi e non passa a una copia: un orario duplicato riparte senza cronologia.
- L'esito dei tentativi di modifica resta nel **Registro delle modifiche** sopra la griglia finché non lo azzeri (**Azzera registro**). È una **cronologia**, non lo stato attuale: una riga «**modifica rifiutata**» vuol dire che l'editor ha detto no e **nulla è cambiato**, quindi può comparire accanto a un Controllo senza problemi. Lo stato vero dell'orario è nel riquadro **Controllo dell'orario**. Ogni messaggio dice **dove** sta il problema: la **classe**, il **giorno e l'ora** (es. «martedì, 3ª ora (09:40–10:30)») e, quando il conflitto è con un'altra lezione, anche **quale** (per esempio «il docente Rossi Anna è già impegnato in 2ª B con Storia»). Se una modifica viene **rifiutata**, la lezione interessata resta com'era (la select torna sulla cattedra reale) e il suo riquadro nella griglia diventa **rosso** con la scritta «Modifica rifiutata: vedi gli avvisi», finché non azzeri gli avvisi. Quando cambi docente o materia di una lezione, gli avvisi sul monte ore sono due e dicono quale è la **cattedra lasciata** (quella che perde un'ora) e quale la **cattedra scelta** (quella che ne guadagna una): l'avviso sul docente che hai appena sostituito non significa che la scelta sia sbagliata. Un **errore** significa che l'operazione è stata rifiutata (per esempio lo spostamento in uno slot in cui il docente è indisponibile); un **avviso** significa che è stata applicata ma da controllare.
<!-- /permesso -->

### Il tabellone (classi e aule)

Dalla scheda di un orario, **Tabellone (classi / aule)** apre tutto l'orario in una pagina, con un **colore per ogni disciplina** (la legenda è in fondo) e due modi di organizzarlo, che scegli in alto:

- **Per classe**: una riga per classe, come il tabellone tradizionale. L'ultima colonna conta i **cambi d'aula** di ogni classe.
- **Per aula**: una riga per aula; in ogni cella vedi **quale classe** c'è, con materia e docente. È la vista più comoda con la didattica **DADA**. Se non indichi nulla, si apre «per aula» nelle scuole dove le classi non hanno un'aula base (DADA) e «per classe» negli altri casi.

La freccia **→** segna le lezioni in cui la classe **cambia aula** rispetto all'ora precedente (nella vista per classe accanto compare il nome abbreviato dell'aula, in quella per aula la freccia sta accanto alla classe e il nome dell'aula di provenienza è nel suggerimento al passaggio del mouse) (la trovi anche nella griglia della classe e nei PDF per classe). Un riquadro con il **bordo rosso** ha un conflitto: passaci sopra per leggerlo. **Esporta PDF** produce il tabellone nell'organizzazione che stai guardando.

<!-- permesso: gestisci-anagrafica -->
**Modificare dal tabellone per aula** (orario in bozza): **trascina** una lezione.

- su **un'altra aula nella stessa ora**: la lezione cambia aula (solo tra aule dei tipi ammessi dalla materia: una lezione di italiano DADA si sposta tra le aule di italiano e quella condivisa);
- su **un'altra ora**: la lezione si sposta (come nella griglia della classe, scambiandosi con la lezione che c'era) e prende l'aula della cella, se è libera, altrimenti un'altra aula libera dello stesso tipo.

Mentre trascini, le celle si colorano: **verde** si può, **ambra** crea un conflitto (aula già occupata, docente occupato: si può fare solo con **Conflitti provvisori** acceso), **rosso** non è ammesso (lezione bloccata, ora fuori scansione, aula di tipo sbagliato). Una materia senza aula speciale può muoversi solo nella riga dell'aula della sua classe. **Annulla** e **Ripeti** valgono anche per i cambi di aula; gli esiti dei tentativi restano nel **Registro delle modifiche** in cima alla pagina.
<!-- /permesso -->

### Controllo dell'orario

In **tutte le viste dell'orario** (griglia della classe, vista docente, vista aula e tabellone) c'è in alto il riquadro **Controllo dell'orario**: è lo **stato reale** dell'orario, filtrato su ciò che stai guardando (i problemi di quella classe, di quel docente, di quell'aula, o di tutto l'orario nel tabellone), **ricalcolato ogni volta che apri o ricarichi la pagina** (non si aggiorna da solo mentre la tieni aperta, e se un collega modifica devi ricaricare). Segnala:

- un **docente in due posti** nello stesso momento (con le due classi);
- un docente **indisponibile** in quell'ora;
- una classe con **due lezioni** nello stesso momento, o una lezione in un'ora **fuori scansione**;
- un'**aula usata da più classi insieme** di quante ne possa ospitare (la capienza dell'aula è il numero di classi contemporanee), una lezione che richiede un tipo di aula ma **non ne ha una assegnata**, o è in un'aula di **tipo diverso**;
- **ore diverse** da quelle previste per una cattedra;
- **ore senza lezione** (avviso).

Ogni riga dice classe, giorno e ora e, quando il problema coinvolge altre classi, c'è il link **Apri 2ª B** per correggerlo lì. I riquadri delle lezioni coinvolte nella griglia sono **rossi**, con il dettaglio passandoci sopra. **Tutto l'orario** apre l'elenco completo; nella pagina *Orari* ogni scheda mostra il riepilogo («Controllo: 2 errori, 1 avviso»). A differenza del registro delle modifiche, un problema **sparisce da solo** quando lo risolvi.

### Fare modifiche all'orario

**Da dove si modifica.** Si modifica da **qualunque vista con i riquadri delle lezioni**, scegliendo quella più comoda per ciò che vuoi fare (l'orario deve essere in **bozza**; nelle altre viste restano gli stessi comportamenti, il Controllo e il registro):

| Vista | Cosa puoi trascinare |
| --- | --- |
| **Griglia della classe** | una lezione su un'altra ora; inoltre cambi docente o materia dal menu della lezione, blocchi e sblocchi |
| **Vista docente** | una lezione su un'altra ora (le ore libere del docente sono destinazioni valide): è la vista giusta per «Rossi deve spostare il martedì» |
| **Vista aula** | una lezione su un'altra ora, restando in quell'aula se è libera |
| **Tabellone per classe** | una lezione su un'altra ora **lungo la riga della sua classe** (rilasciarla in un'altra riga non fa nulla) |
| **Tabellone per aula** | su un'altra aula nella stessa ora (cambio di aula) o su un'altra ora (spostamento) |

In ogni vista trovi in alto gli stessi strumenti: **Conflitti provvisori**, **Annulla** e **Ripeti** (e le scorciatoie Ctrl/Cmd+Z e Ctrl/Cmd+Maiusc+Z), che valgono per tutte le modifiche, comunque fatte; gli esiti dei tentativi restano nel **Registro delle modifiche** in cima a ogni vista. Quando due lezioni della stessa classe si trovano a scambiarsi di posto, lo scambio è un'unica modifica.

1. **Stessa classe, spostare una lezione**: trascinala. Mentre la trascini gli slot si colorano: **verde** = si può fare, **ambra** = crea un conflitto (si può fare solo con i conflitti provvisori, vedi sotto), **rosso** = non ammesso (lezione bloccata, ora fuori scansione, o conflitto con la modalità spenta). Passando col mouse su uno slot vedi il motivo.
2. **Cambiare docente o materia di una lezione**: scegli un'altra cattedra della **stessa classe** dal menu della lezione.
3. **Scambi tra classi** (per esempio due docenti che devono scambiarsi le ore, o un docente che deve lasciare una lezione): spesso il primo spostamento crea un conflitto con l'altra classe e va completato lì. Accendi **Conflitti provvisori** (sopra la griglia): le modifiche con conflitti di docente o aula vengono accettate e il conflitto resta **segnalato in rosso nel Controllo**, con il link alla classe da sistemare. Apri quella classe, sposta le sue lezioni e il controllo si svuota da solo quando tutto torna coerente. Spegni l'interruttore a fine lavoro per tornare alla modalità prudente, che rifiuta ogni conflitto.
4. **Annulla / Ripeti** vale per tutti i passaggi, anche quelli provvisori.
5. Per **provare una variante** o un cambio temporaneo senza toccare l'orario in vigore, **duplica** l'orario (vedi sopra) e lavora sulla copia.

I conflitti provvisori non permettono mai di spostare una lezione bloccata o in un'ora che non fa parte della classe. Un orario con errori nel Controllo non va mandato in revisione.

### Esportare in PDF

- **Per classe** e **per docente**: dal pulsante "Esporta PDF" della griglia. Un foglio A4 orizzontale con il titolo al centro e tutta la settimana; le righe delle ore dopo l'ultima usata non compaiono.
- **Tutte le classi** (pulsante **Tutte le classi** nella pagina *Orari*, "Classi PDF" nella dashboard): un solo PDF A4 orizzontale con **un foglio per classe**, ciascuno con il titolo centrale della classe, tutta la settimana e i docenti di sostegno in compresenza ("S Cognome"). Comodo per stampare gli orari da affiggere nelle aule.
- **Tutte le aule** (pulsante **Tutte le aule** nella pagina *Orari*) e **una sola aula** ("Esporta PDF" della vista aula): un PDF A4 orizzontale con **un foglio per aula**, in ordine alfabetico, con classe, materia e docente di ogni ora: è il foglio da appendere alla porta. Compaiono solo le aule usate in quell'orario. Nei PDF per classe e per docente l'aula è scritta sotto ogni lezione, con le stesse regole delle griglie.
- **Tutti i docenti** (pulsante **Tutti i docenti** nella pagina *Orari*, "Docenti PDF" nella dashboard): un solo PDF A4 orizzontale con **un foglio per docente**, in ordine alfabetico, ciascuno con il titolo centrale, tutta la settimana e in ogni ora classe e materia. Le ore di **sostegno in compresenza** compaiono come "S classe". Compaiono solo i docenti che hanno almeno un'ora in quell'orario. Comodo per consegnare a ciascuno il proprio orario.
- **Tabellone per aula** (pulsante **Tabellone per aula** nella scheda dell'orario): come il tabellone generale ma con una riga per aula; in ogni cella la classe e la sigla della materia. Come l'altro, ha un colore per ogni disciplina.
- **Tabellone generale**: un solo foglio A3, una riga per classe e le colonne divise per giorno, tutte della stessa larghezza. Mostra la sigla della materia e il cognome del docente (troncati con "…" se lunghi); i docenti di **sostegno** in compresenza compaiono come "S Cognome". In fondo c'è la legenda delle sigle.

## Ruoli e permessi

Stai usando l'applicazione come **{ruolo}**. Il ruolo decide quali voci del menu vedi e cosa puoi modificare.

| Ruolo | Cosa può fare |
| --- | --- |
| Amministratore | Tutto, comprese le utenze, l'esportazione e l'importazione dei dati, l'approvazione degli orari e il registro attività |
| Referente Orario | Anagrafiche, vincoli, generazione ed editor dell'orario |
| Segreteria | Consulta tutto; gestisce docenti e classi |
| Dirigente Scolastico | Consulta tutto; approva, pubblica e archivia gli orari; consulta il registro attività |
| Referente Sostituzioni | Consulta tutto |
| Docente | Accesso base, collegato alla propria anagrafica |

Le voci del menu e i pulsanti che non vedi dipendono dal ruolo: chiedi all'amministratore se ti serve un ruolo diverso.

## Utenze
<!-- sezione: gestisci-utenze -->

Le utenze sono account locali. Solo l'**amministratore** vede la voce **Utenze** (nel menu del profilo, in alto a destra), dove crea, modifica ed elimina gli accessi. Gli account **non** passano con *Dati: esporta e importa*: su un'altra installazione vanno creati da qui.

Campi di un'utenza:

- **Nome**: come viene mostrato nell'applicazione.
- **Email**: è il nome utente per accedere; deve essere unica.
- **Ruolo**: cosa può fare (vedi tabella).
- **Docente collegato**: solo per il ruolo Docente, collega l'accesso alla sua scheda. L'elenco mostra i docenti della sede in cui stai lavorando: per collegare un account a un docente di un'altra sede, cambia prima sede.
- **Password**: almeno 8 caratteri. In modifica, lasciandola vuota, quella esistente non cambia.

Non puoi eliminare la tua utenza né toglierti il ruolo di amministratore.

## Dati: esporta e importa
<!-- sezione: gestisci-utenze -->

Questa pagina, riservata all'**amministratore**, serve a fare un **backup** dei dati della scuola o a **trasferirli** su un'altra installazione. Il registro delle attività e le **utenze** (account e password) non sono compresi: gli account si creano e si gestiscono da *Utenze* su ciascuna installazione.

- **Esporta:** spunta le tabelle che vuoi (di norma tutte) e premi **Scarica lo ZIP**. Il file contiene un JSON per tabella e comprende **tutte le sedi** (l'esportazione non dipende dalla sede in cui stai lavorando; i CSV invece sì): custodiscilo comunque con cura, ha tutti i dati della scuola.
- **Importa:** carica uno ZIP esportato da Orario Scuola. Nel passo successivo vedi cosa contiene e scegli le tabelle da importare; le tabelle spuntate vengono **sostituite** con quelle dell'archivio. Prima di sostituire ti conviene scaricare un backup dei dati attuali. L'import avviene in un colpo solo: se qualcosa non va, **non cambia nulla**.
- **Tabelle collegate:** molte tabelle dipendono da altre (le cattedre da docenti, classi e discipline, per esempio). Se importi solo una parte e i riferimenti non tornano, l'import si ferma con un messaggio che dice cosa manca: in quel caso importa anche le tabelle collegate, o tutte.
- **Limiti:** l'archivio deve venire da questa versione o da una precedente; se viene da una più recente, aggiorna prima l'installazione. L'import non parte se c'è una generazione in corso. Chi aveva creato un orario o una generazione nell'archivio, se non esiste su questa installazione, resta senza autore; un account collegato a un docente che l'archivio non contiene perde il collegamento.
- **Altro formato:** per un backup completo del database (per chi gestisce il server) resta possibile `docker compose exec db mariadb-dump …`.

## Registro attività
<!-- sezione: approva-orari -->

Il **Registro attività** (nel menu del profilo in alto a destra, per il dirigente scolastico e l'amministratore) elenca **tutto ciò che viene fatto** nell'applicazione: ogni **creazione**, **modifica** ed **eliminazione** di sedi, aule, discipline, quadri orari, docenti, classi, cattedre, sostegno, vincoli e utenze; ogni **generazione** dell'orario; i cambi di stato, le duplicazioni e le modifiche manuali alla griglia (con annullamenti e ripristini).

- Per ogni voce vedi **quando**, **chi** (le operazioni automatiche risultano come *Sistema*), l'**azione**, l'**elemento** con il suo nome e il **dettaglio**: per le modifiche i valori *prima → dopo*.
- Il nome dell'elemento resta leggibile anche dopo che è stato eliminato.
- Puoi filtrare per elemento, azione, utente, periodo e nome. Le voci più recenti stanno in cima.
- Le password non vengono mai registrate (compare solo `***`).
- Il registro **non si può modificare né cancellare** dall'applicazione.
- Non sono registrate le scelte multiple fatte con le caselle (per esempio le sedi di un docente o le indisponibilità) e il caricamento della scuola di esempio.

## Versione e aggiornamenti
<!-- sezione: gestisci-utenze -->

In fondo alla barra laterale compare la **versione** installata (per esempio *Versione 0.1.0*). Se nel progetto su GitHub il numero di versione (il file `VERSION` del ramo principale) è **più alto** di quello installato, **l'amministratore** vede in alto a destra, accanto al pulsante **Aiuto**, un pulsante giallo lampeggiante «Disponibile la versione X»: cliccandolo si apre la pagina del progetto. Non servono tag né release: basta che il file `VERSION` online sia aggiornato.

- Il controllo è l'**unica connessione verso l'esterno** dell'applicazione: legge il file `VERSION` pubblico del progetto, non invia alcun dato della scuola, avviene quando **apri la dashboard** (quindi a ogni accesso), senza memorizzare nulla e, se manca la rete, semplicemente non succede nulla. Il pulsante resta visibile nelle altre pagine fino alla fine della sessione; se esce una versione mentre sei già collegato, lo vedi alla prossima apertura della dashboard (GitHub può impiegare qualche minuto a pubblicare il file).
- Per **aggiornare**: apri `WIN-Orario-Scuola.bat` (Windows) o `MAC-Orario-Scuola.app` (Mac) nella cartella del programma, con Docker acceso, e premi **Aggiorna**. Scarica da GitHub l'ultima versione (non serve Git) e ricostruisce l'applicazione: ci vuole qualche minuto e i dati nel database restano. La cartella del programma viene allineata alla nuova versione (i file che non esistono più vengono cancellati): non tenerci dentro file tuoi. Poi ricarica la pagina.
- Per **disattivare** il controllo, chi gestisce l'installazione imposta `CONTROLLO_AGGIORNAMENTI=false` nel file di configurazione.

## Consigli d'uso
<!-- sezione: gestisci-docenti-classi -->

- **Modali**: le schede di creazione e modifica semplici si aprono in una finestra. Si chiude con **×**, **Annulla** o **Esc**; un click fuori non la chiude, così non perdi i dati. Salvando la finestra si chiude. **Salva** è sempre in basso a destra. Docenti e classi si modificano su pagina intera.
- **Eliminare**: nelle tabelle spunta le righe e premi **Elimina selezionati**. Il pulsante è disabilitato finché non selezioni almeno una riga. Gli elementi ancora in uso non vengono eliminati e ti viene detto quanti.
- **Notifiche**: conferme ed errori compaiono in alto a destra come piccoli messaggi che spariscono dopo 10 secondi. Passandoci sopra con il mouse il tempo si ferma; la **×** li chiude subito. Gli avvisi sulle modifiche all'orario, invece, restano nel pannello sopra la griglia finché non li azzeri.
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
- **Laboratorio**: attività pomeridiana (es. Latino) assegnata a mano, fuori dal monte ore e dalla generazione, che occupa docenti e aula.
- **Registro attività**: l'elenco, non modificabile, di chi ha fatto che cosa e quando.
- **Supplente**: docente a cui passano le cattedre di un titolare sospeso o assente a lungo; si indica sulla sospensione (scheda del docente).
- **Orario**: il risultato di una generazione: l'elenco delle lezioni nei vari slot. Ha uno **stato** (bozza, in revisione, approvato, pubblicato, archiviato) e una **versione**.
- **Ore a disposizione**: ore dovute dal docente non coperte da lezioni (ore dovute − ore di cattedra); servono ad esempio per le sostituzioni.
- **Ore dovute**: ore settimanali di lezione previste dal contratto del docente.
- **Part-time orizzontale / verticale / misto**: orario ridotto tutti i giorni / lavoro solo in alcuni giorni / combinazione delle due forme.
- **Potenziamento**: organico dell'autonomia; ore per progetti e sostituzioni.
- **Quadro orario**: il monte ore settimanale per disciplina di una classe.
- **Rientro pomeridiano**: giorno in cui una classe a tempo prolungato fa lezione anche il pomeriggio.
- **Ricreazione** (o **pausa**): intervallo tra due ore di lezione; nella scansione oraria si indicano i minuti sull'ora che la precede e si può dare un **nome** (per esempio «Mensa»). Compare nei PDF con nome, orario e durata.
- **Assistenza alle pause**: sorveglianza degli alunni da parte di un docente durante una pausa, per esempio la mensa; si assegna per giorno e pausa nella scheda del docente e vale per tutti gli orari.
- **Seed**: numero che rende riproducibile una generazione; lo scegli da un elenco (casuale o uno già usato).
- **Slot**: una singola ora della settimana (giorno + ora, es. martedì 3ª ora). La scansione oraria di istituto è l'insieme di tutti gli slot.
- **Sostegno**: supporto agli alunni con disabilità; nell'applicazione si indicano solo il fabbisogno orario (con codici anonimi) e i docenti assegnati.
- **Tabellone**: il PDF con l'orario di tutte le classi su un unico foglio.
- **Tempo normale / prolungato**: classi con 30 ore solo al mattino / con più ore e rientri pomeridiani.
- **Vincolo rigido / preferenziale**: regola da rispettare sempre / regola da rispettare quando possibile, con un peso.
- **Worker di coda**: il programma di servizio che esegue in background le generazioni dell'orario.

## Problemi frequenti

<!-- permesso: gestisci-anagrafica -->
- **La generazione resta "in coda".** Il worker di coda è fermo (di solito parte da solo con **Avvia generazione**): in **Genera orario** premi **Avvia**.
<!-- /permesso -->
<!-- permesso: consulta -->
- **La generazione è "infattibile".** Leggi i messaggi nella pagina della generazione: accanto a quelli che si sa dove correggere c'è il pulsante **Correggi**. Le cause più comuni: le ore delle cattedre di una classe non coincidono con il quadro orario; gli slot attivi della classe non coincidono con le ore del quadro; a un docente sono state assegnate più ore degli slot in cui è disponibile; non ci sono abbastanza aule di un tipo; due vincoli rigidi si contraddicono; le ore di sostegno assegnate non coprono il fabbisogno.
<!-- /permesso -->
<!-- permesso: gestisci-docenti-classi -->
- **Non riesco a eliminare un elemento.** È ancora in uso: ad esempio un quadro orario usato da classi. Il messaggio dopo l'eliminazione dice quanti elementi non sono stati eliminati.
<!-- /permesso -->
<!-- permesso: gestisci-docenti-classi -->
- **Ho eliminato un docente o una classe per errore.** Con loro vengono eliminate anche le cattedre collegate e le lezioni degli orari già generati che le usavano. Controlla sempre la conferma prima di eliminare.
<!-- /permesso -->
<!-- permesso: consulta -->
- **Non riesco a modificare la griglia di un orario.** Si modifica solo in stato *Bozza*: se l'orario è in revisione, approvato, pubblicato o archiviato usa **Duplica** (nasce una nuova bozza) oppure, se ne hai il permesso, riportalo in bozza.
<!-- /permesso -->
- **Non vedo un pulsante o una voce del menu.** Dipendono dal tuo ruolo: chiedi all'amministratore se ti serve un ruolo diverso.
<!-- permesso: gestisci-docenti-classi -->
- **Un campo è grigio e non posso modificarlo.** Passa il mouse sull'icona **i** accanto: dice cosa impostare per attivarlo (per esempio il peso di un vincolo richiede Severità = Preferenziale).
<!-- /permesso -->
<!-- permesso: gestisci-anagrafica -->
- **Una modifica all'orario ha prodotto un errore o un avviso.** Leggi il Registro sopra la griglia: «modifica rifiutata» vuol dire che la modifica non è stata fatta, un avviso che è stata fatta ma va controllata. Restano lì finché non li azzeri.
<!-- /permesso -->
<!-- permesso: consulta -->
- **Il PDF non mostra le ore del pomeriggio.** Le ore senza lezioni non compaiono: se nessuna classe ha lezione il pomeriggio, le colonne sono nascoste.
<!-- /permesso -->
