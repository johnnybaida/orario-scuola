"""D1_BLOCCO_MIN_CONSECUTIVO — almeno n_blocchi_min giorni con un blocco di
>= min_consecutive ore consecutive. Con ambito classe/globale conta le ore
della `disciplina` in ciascuna classe; con ambito docente conta le lezioni
del docente (della `disciplina` se indicata, altrimenti tutte, in qualunque
classe).

ponytail: conta i GIORNI che hanno almeno una finestra valida, non il numero
esatto di blocchi nel giorno (due blocchi lo stesso giorno contano 1 volta).
Caso raro, sufficiente per l'MVP; se serve il conteggio esatto, va introdotta
una variabile per blocco invece che per giorno.
"""

from constraints.util import reify_and, reify_or, slack_deficit


def _classi_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'classe':
        return ambito['ids']
    return list(ctx.classi_slot_attivi.keys())


def _occupazione_docente(ctx, docente_id, disciplina):
    """slot -> letterale vero se il docente ha lezione in quello slot (della disciplina, se indicata)."""
    if disciplina is None:
        return ctx.docente_occ.get(docente_id, {})
    per_slot = {}
    for lez in ctx.lezioni:
        if docente_id in lez['docenti'] and lez['disciplina'] == disciplina:
            for slot, occ in ctx.occupato[lez['id']].items():
                per_slot.setdefault(slot, []).append(occ)
    return {s: (lits[0] if len(lits) == 1 else reify_or(ctx.model, lits)) for s, lits in per_slot.items()}


def _penalita_blocchi(ctx, vincolo, occ, min_consecutive, n_blocchi_min):
    block_giorno = []
    for giorno in ctx.giorni:
        slot_giorno = ctx.slots_by_day[giorno]
        finestre_ok = []
        for i in range(len(slot_giorno) - min_consecutive + 1):
            finestra = slot_giorno[i:i + min_consecutive]
            lits = [occ[s] for s in finestra if s in occ]
            if len(lits) != len(finestra):
                continue
            finestre_ok.append(reify_and(ctx.model, lits))
        block_giorno.append(reify_or(ctx.model, finestre_ok))

    n_blocchi = sum(block_giorno)

    if vincolo['severita'] == 'rigido':
        ctx.model.Add(n_blocchi >= n_blocchi_min)
        return []
    deficit = slack_deficit(ctx.model, n_blocchi, n_blocchi_min)
    return [vincolo['peso'] * deficit]


def applica(ctx, vincolo):
    parametri = vincolo['parametri']
    disciplina = parametri.get('disciplina')
    min_consecutive = parametri['min_consecutive']
    n_blocchi_min = parametri.get('n_blocchi_min', 1)

    penalita = []
    if vincolo['ambito']['livello'] == 'docente':
        for docente_id in vincolo['ambito']['ids']:
            occ = _occupazione_docente(ctx, docente_id, disciplina)
            if occ:
                penalita += _penalita_blocchi(ctx, vincolo, occ, min_consecutive, n_blocchi_min)
        return penalita

    if disciplina is None:
        return []   # per classi e globale la disciplina è obbligatoria (la valida il PHP)
    for classe_id in _classi_target(ctx, vincolo):
        occ = ctx.disc_occ.get((classe_id, disciplina))
        if occ:
            penalita += _penalita_blocchi(ctx, vincolo, occ, min_consecutive, n_blocchi_min)

    return penalita
