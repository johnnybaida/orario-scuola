# Guida all'uso di Orario Scuola

Questa guida è il manuale dell'applicazione. Apri il pannello con il pulsante **Aiuto** in alto a destra o con il tasto **F1**: si apre già sull'argomento della pagina in cui ti trovi. Cambia argomento dal menu oppure scrivi nel campo **Cerca nella guida**: l'elenco mostra le sezioni che contengono le parole cercate e, aprendone una, le evidenzia. Per chiudere il pannello usa la **×**, **Esc** o di nuovo **F1**.

## Per iniziare

L'applicazione genera e gestisce l'orario settimanale di una scuola secondaria di primo grado. Il percorso tipico è questo, in ordine:

1. **Sedi/Aule**: i plessi dell'istituto e le loro aule.
2. **Discipline**: le materie, con classe di concorso e aula richiesta.
3. **Quadri orari**: quante ore settimanali di ogni disciplina fa una classe.
4. **Docenti** e **Classi**: le anagrafiche.
5. **Cattedre**: chi insegna cosa, in quale classe e per quante ore.
6. **Vincoli** (facoltativo): regole in più per la generazione.
7. **Genera orario**: avvia il calcolo automatico.
8. **Orari**: controlla, correggi a mano ed esporta in PDF.

Per ogni pagina trovi anche una breve guida in alto. Le voci del menu a sinistra che non vedi dipendono dal tuo ruolo (vedi *Utenze e ruoli*).

## Sedi e aule

Le **sedi** sono i plessi: se l'istituto ne ha uno solo, basta una sede. Ogni **aula** appartiene a una sede e ha un **tipo** e una **capienza**.

- **Tipo**: scegli dall'elenco (classe, laboratorio, palestra, aula di musica, ...). Il tipo serve a collegare le discipline che richiedono un'aula speciale.
- **Capienza**: quante lezioni possono svolgersi nello stesso momento in aule di quel tipo (1 = una classe alla volta).

### Didattica DADA

Con la didattica DADA le classi non hanno un'aula fissa: **sono gli alunni a spostarsi** nell'aula della disciplina.

1. Vai in **Sedi/Aule** e crea un'aula.
2. Come tipo scegli **"DADA · nome della disciplina"**.
3. La disciplina viene collegata automaticamente a quell'aula. Nella colonna "Usata da" vedi quali discipline si svolgono in ciascuna aula.
4. Nelle classi lascia vuoto il campo **Aula base**.

## Discipline

Il catalogo delle materie insegnate. Per ognuna indichi codice (la sigla usata nel tabellone, es. ITA), nome, classe di concorso e l'eventuale **aula richiesta**.

- **Aula richiesta**: l'elenco contiene i tipi di aula già censiti. Se scegli "Aula della classe" la lezione si svolge nella classe.
- **Sotto-disciplina di**: collega materie insegnate dallo stesso docente (es. Storia e Geografia sotto Italiano). È solo informativo.

## Quadri orari

Un quadro orario è il monte ore settimanale per disciplina (es. "Tempo normale 30h"). Ogni classe ne usa uno.

- Aggiungi le discipline con **+ Aggiungi disciplina**, indica le ore e salva una volta sola. Il **totale** si aggiorna mentre scrivi.
- Le cattedre di una classe devono coprire esattamente le ore del suo quadro, altrimenti la generazione segnala un'incoerenza.
- Un quadro usato da qualche classe **non si può eliminare**.

## Docenti

L'anagrafica comprende tipo di posto (comune, sostegno, potenziamento, IRC, strumento), regime orario e **ore dovute** (18 = cattedra intera).

Nella pagina del docente puoi:

- scegliere le **classi di concorso** abilitanti (prese dalle discipline) e le **sedi di servizio**;
- impostare le **indisponibilità**: gli slot in cui il docente non può avere lezione (part-time, servizio in altre scuole). Il generatore e l'editor le rispettano sempre;
- gestire le **cattedre**: aggiungi o togli righe e guarda il totale "assegnate / dovute", che diventa ambra se non coincide.

Salva con il pulsante **Salva** in basso a destra: un solo salvataggio vale per tutta la pagina. Puoi importare più docenti insieme da un file **CSV** (colonne: nome, cognome, email, tipo_contratto, tipo_posto, regime, ore_dovute).

## Classi

Ogni classe ha anno, sezione, sede, quadro orario, tempo scuola (normale o prolungato) e numero di alunni. Gli alunni non sono censiti: conta solo il numero.

### Rientri pomeridiani

Con il **tempo prolungato** puoi scegliere i **giorni di rientro**, ognuno in modo indipendente (per esempio martedì e giovedì per una classe, lunedì e mercoledì per un'altra). Spuntando un giorno si attivano le sue ore pomeridiane (7ª–9ª) nella griglia degli slot attivi. Il campo è attivo solo con Tempo scuola = Prolungato.

### Slot attivi

La griglia degli **slot attivi** indica le ore della settimana che la classe usa davvero. Il generatore copre esattamente quegli slot. Puoi modificare le singole ore a mano, per casi particolari.

### Cattedre

Nella pagina della classe aggiungi o togli le cattedre (disciplina, docente, ore, compresenza). Il totale mostra le ore assegnate rispetto al quadro orario.

### Sostegno

Gli alunni con sostegno non sono censiti: ogni **fabbisogno** ha solo un codice anonimo (es. `1B-S1`) e le ore settimanali. Poi assegni i **docenti di sostegno** con le loro ore.

- **Conteggio ore**: "per alunno" somma i fabbisogni, "per classe" prende il più alto. Puoi usare il valore predefinito dell'istituto.
- **Docente unico**: il fabbisogno deve essere coperto da un solo docente.
- Il generatore programma le **compresenze** di sostegno. Il totale "assegnate / richieste" ti dice se le ore bastano.
- L'elenco dei docenti assegnabili contiene solo chi ha tipo posto **Sostegno**.

## Cattedre

Una cattedra assegna un docente a una disciplina per una classe, con un numero di ore settimanali. La pagina **Cattedre** è l'elenco completo, filtrabile per classe e per docente. Puoi anche modificarle dalle pagine di docente e classe.

La somma delle cattedre di un docente non dovrebbe superare le sue ore dovute; quella di una classe deve coincidere col quadro orario.

## Vincoli

I vincoli sono regole aggiuntive per la generazione, oltre a quelle di sistema sempre attive (un docente non può essere in due posti insieme, una classe ha una sola lezione per slot, le indisponibilità sono rispettate, ecc.).

- **Rigido**: va rispettato sempre; se non è possibile, l'orario risulta infattibile.
- **Preferenziale**: ha un **peso** da 1 a 100; il generatore cerca di rispettarlo e lo viola solo se non c'è alternativa migliore. Il peso si attiva solo con Severità = Preferenziale.
- **Ambito**: globale (tutti), oppure classi o docenti specifici. Le classi si scelgono solo con Ambito = Classe, i docenti solo con Ambito = Docente. Il vincolo più specifico prevale su quello globale.

Tipi disponibili:

- **Blocco consecutivo minimo (D1)**: una disciplina in blocchi di almeno N ore consecutive.
- **Max ore/giorno per disciplina (D3)**: limite di ore al giorno della stessa disciplina.
- **Fascia oraria vietata/preferita (D6)**: ore in cui una disciplina non va (o è preferibile) collocata.
- **Giorno libero (T2)**: un docente ha uno o più giorni liberi, con giorno preferito facoltativo.
- **Max ore buche (T3)**: limite alle ore vuote di un docente al giorno e alla settimana.

## Genera orario

La generazione avviene **in background**: avvia il calcolo e segui l'avanzamento nella pagina.

1. Controlla in alto lo stato del **worker di coda**. Se è "fermo", premi **Avvia**: senza worker le generazioni restano in coda. Per fermarlo usa **Ferma**: termina prima il job in corso. Se lo vedi "in arresto" puoi già riavviarlo.
2. Premi **Nuova generazione** e indica il **tempo limite** in secondi (più tempo, orari migliori) e, se vuoi, un **seed**.
3. Il **seed** rende il risultato riproducibile: stessi dati e stesso seed producono lo stesso orario. Lascialo vuoto per uno casuale; ogni generazione registra il seed usato.

Prima del calcolo l'applicazione controlla i dati (capacità dei docenti e delle aule, coerenza tra quadri orari e cattedre). Se non esiste un orario valido, la pagina della generazione elenca **quali vincoli o risorse sono in conflitto**.

## Orari e modifica manuale

La pagina **Orari** elenca gli orari prodotti. Da lì puoi:

- aprire la griglia di una classe (modificabile) o di un docente (sola lettura), con le **select di ricerca**: scrivi parte del nome per trovare la voce;
- scaricare il **tabellone generale in PDF**;
- eliminare un orario selezionandolo con la casella a sinistra (la generazione resta nello storico).

### Griglia della classe

- **Trascina** una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si **scambiano**.
- Il menu dentro la lezione cambia **materia e/o docente** (cerca per materia o per docente).
- **Blocca/Sblocca**: una lezione bloccata non si sposta, non si scambia e non si modifica.
- **Annulla ultima modifica** ripristina l'ultima operazione.
- L'esito delle modifiche (errori e avvisi, per esempio un conflitto di docente) resta nel **pannello degli avvisi** sopra la griglia finché non lo azzeri.

### Esportare in PDF

- **Per classe** e **per docente**: dal pulsante "Esporta PDF" della griglia. Le righe delle ore dopo l'ultima usata non compaiono.
- **Tabellone generale**: un solo foglio A3, una riga per classe e le colonne divise per giorno, tutte della stessa larghezza. Mostra la sigla della materia e il cognome del docente (troncati con "…" se lunghi); i docenti di **sostegno** in compresenza compaiono come "S Cognome". In fondo c'è la legenda delle sigle.

## Utenze e ruoli

Le utenze sono account locali. Solo l'**amministratore** vede la voce **Utenze**, dove crea, modifica ed elimina gli accessi.

| Ruolo | Cosa può fare |
| --- | --- |
| Amministratore | Tutto, comprese le utenze |
| Referente Orario | Anagrafiche, vincoli, generazione ed editor dell'orario |
| Segreteria | Consulta tutto; gestisce docenti e classi |
| Dirigente Scolastico | Consulta tutto |
| Referente Sostituzioni | Consulta tutto |
| Docente | Accesso base, collegato alla propria anagrafica |

Per un'utenza **Docente** puoi collegare l'accesso alla sua scheda docente. In modifica, lasciando vuota la password, quella esistente non cambia. Non puoi eliminare la tua utenza né toglierti il ruolo di amministratore.

## Consigli d'uso

- **Modali**: le schede di creazione e modifica semplici si aprono in una finestra. Si chiude con **×**, **Annulla** o **Esc**; un click fuori non la chiude, così non perdi i dati. **Salva** è sempre in basso a destra.
- **Eliminare**: nelle tabelle spunta le righe e premi **Elimina selezionati**. Il pulsante è disabilitato finché non selezioni almeno una riga. Gli elementi ancora in uso non vengono eliminati e ti viene detto quanti.
- **Controlli disabilitati**: se un campo è grigio e vedi un'icona **i**, passaci sopra: spiega cosa impostare per attivarlo.
- **Righe ripetibili** (cattedre, discipline del quadro, sostegno): aggiungi o rimuovi righe con i pulsanti della sezione e salva con il pulsante unico in basso a destra.
- **Ore pomeridiane**: ogni giorno offre le ore 7ª–9ª; sta alle classi attivarle con i rientri.
