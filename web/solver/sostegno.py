"""Compresenze di sostegno (§5.6 dell'analisi). Il docente di sostegno non
insegna una disciplina propria: le sue ore sono compresenze con le lezioni
curricolari già presenti in ogni slot attivo della classe (H5 garantisce che
ce ne sia sempre una). Integrato nello stesso modello CP-SAT di
contesto.py così l'esclusività del docente (H2) vale anche qui.

conteggio "per_alunno": ogni ora di un docente in compresenza vale per un
solo alunno (fabbisogno/codice) — supporta anche S4 "docente unico".
conteggio "per_classe": un docente presente quell'ora vale per tutti i
fabbisogni della classe insieme; il totale di ore-compresenza pianificate è
il fabbisogno più alto tra i codici della classe (semplificazione: gli
alunni con fabbisogno minore ricevono più assistenza del minimo richiesto,
non meno).

Senza fabbisogni (alunni non censiti) ogni docente assegnato è in compresenza per le sue ore, con codice None.

Ritorna (assegnazioni, diagnostica): assegnazioni è una lista di
(docente_id, classe_id, slot_id, codice_o_None, BoolVar) da leggere dopo il
solve per produrre 'compresenze_sostegno'.
"""

from constraints.util import reify_or


def applica(ctx, sostegno_lista):
    diagnostica = []
    risultato = []
    presenze = {}   # (docente, slot) -> presenze in compresenza nelle varie classi
    ctx.sostegno_docente = presenze   # (docente, slot) -> presenze in sostegno: le legge T11
    ctx.sostegno_presenze = {}   # classe -> {'slot': {slot: [presente]}, 'ore': ore totali dei docenti}: lo legge S5

    for entry in sostegno_lista:
        classe_id = entry['classe']
        fabbisogni = entry['fabbisogni']
        assegnazioni = entry['docenti']
        conteggio = entry.get('conteggio', 'per_alunno')

        slot_attivi = list(ctx.classi_slot_attivi.get(classe_id, set()))

        # docente -> {slot: BoolVar} "presente in compresenza in questa classe quello slot"
        copre_classe = {}
        for assegnazione in assegnazioni:
            docente_id = assegnazione['id']
            slot_validi = [s for s in slot_attivi if s not in ctx.docenti_indisponibili.get(docente_id, set())]
            vars_slot = {s: ctx.model.NewBoolVar(f'sost_D{docente_id}_C{classe_id}_S{s}') for s in slot_validi}
            copre_classe[docente_id] = vars_slot

            if len(vars_slot) < assegnazione['ore']:
                diagnostica.append(
                    f"Sostegno classe {classe_id}: il docente {docente_id} ha solo {len(vars_slot)} slot "
                    f"disponibili ma gli sono state assegnate {assegnazione['ore']}h."
                )
                continue
            ctx.model.Add(sum(vars_slot.values()) == assegnazione['ore'])

        per_slot = ctx.sostegno_presenze.setdefault(classe_id, {'slot': {}, 'ore': 0})
        per_slot['ore'] += sum(a['ore'] for a in assegnazioni)
        for docente_id, vars_slot in copre_classe.items():
            for s, v in vars_slot.items():
                presenze.setdefault((docente_id, s), []).append(v)
                per_slot['slot'].setdefault(s, []).append(v)

        # H2: un docente non può essere in compresenza e in una lezione curricolare
        # (o in un'altra classe in compresenza) nello stesso slot.
        for docente_id, vars_slot in copre_classe.items():
            for s, v in vars_slot.items():
                occ_curricolare = ctx.docente_occ.get(docente_id, {}).get(s)
                if occ_curricolare is not None:
                    ctx.model.Add(v + occ_curricolare <= 1)

        if not fabbisogni:
            # Nessun fabbisogno (alunni non censiti): le ore assegnate ai docenti sono il bisogno. Ognuno è in compresenza esattamente per le
            # sue ore (imposto sopra), senza il codice di un alunno.
            risultato.extend(
                (docente_id, classe_id, s, None, v)
                for docente_id, vars_slot in copre_classe.items()
                for s, v in vars_slot.items()
            )
            continue

        if conteggio == 'per_classe':
            risultato.extend(_applica_per_classe(ctx, classe_id, slot_attivi, copre_classe, fabbisogni))
        else:
            risultato.extend(_applica_per_alunno(ctx, classe_id, copre_classe, fabbisogni))

    # H2 tra classi: lo stesso docente di sostegno non può essere in compresenza in due classi nello stesso slot.
    for lits in presenze.values():
        if len(lits) > 1:
            ctx.model.Add(sum(lits) <= 1)

    return risultato, diagnostica


def _applica_per_alunno(ctx, classe_id, copre_classe, fabbisogni):
    # (docente_id, slot_id) -> {codice: BoolVar}
    copertura = {}

    for fabbisogno in fabbisogni:
        codice = fabbisogno['codice']
        docente_unico = fabbisogno.get('docente_unico', False)

        assegnato_docente = {}
        if docente_unico:
            for docente_id in copre_classe:
                assegnato_docente[docente_id] = ctx.model.NewBoolVar(f'sost_unico_D{docente_id}_{codice}')
            ctx.model.AddExactlyOne(assegnato_docente.values())

        lits_codice = []
        for docente_id, vars_slot in copre_classe.items():
            for s, presente in vars_slot.items():
                v = ctx.model.NewBoolVar(f'sost_cod_D{docente_id}_{codice}_S{s}')
                ctx.model.Add(v <= presente)
                if docente_unico:
                    ctx.model.Add(v <= assegnato_docente[docente_id])
                copertura.setdefault((docente_id, s), {})[codice] = v
                lits_codice.append(v)

        if lits_codice:
            ctx.model.Add(sum(lits_codice) == fabbisogno['ore'])

    # per ogni slot, un docente copre al massimo un codice quella volta, ed
    # esattamente uno se è presente (nessuna presenza "a vuoto" in questa modalità).
    for docente_id, vars_slot in copre_classe.items():
        for s, presente in vars_slot.items():
            lits = list(copertura.get((docente_id, s), {}).values())
            if lits:
                ctx.model.Add(sum(lits) == presente)

    return [
        (docente_id, classe_id, s, codice, v)
        for (docente_id, s), per_codice in copertura.items()
        for codice, v in per_codice.items()
    ]


def _applica_per_classe(ctx, classe_id, slot_attivi, copre_classe, fabbisogni):
    if not fabbisogni:
        return []

    ore_bersaglio = max(f['ore'] for f in fabbisogni)

    qualcuno_presente = {}
    for s in slot_attivi:
        lits = [vars_slot[s] for vars_slot in copre_classe.values() if s in vars_slot]
        if lits:
            qualcuno_presente[s] = reify_or(ctx.model, lits)

    if qualcuno_presente:
        ctx.model.Add(sum(qualcuno_presente.values()) == ore_bersaglio)

    return [
        (docente_id, classe_id, s, fabbisogno['codice'], v)
        for docente_id, vars_slot in copre_classe.items()
        for s, v in vars_slot.items()
        for fabbisogno in fabbisogni
    ]
