"""D1_BLOCCO_MIN_CONSECUTIVO — almeno n_blocchi_min giorni con un blocco di
>= min_consecutive ore consecutive della disciplina, per le classi indicate.

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


def applica(ctx, vincolo):
    parametri = vincolo['parametri']
    disciplina = parametri['disciplina']
    min_consecutive = parametri['min_consecutive']
    n_blocchi_min = parametri.get('n_blocchi_min', 1)

    penalita = []
    for classe_id in _classi_target(ctx, vincolo):
        occ = ctx.disc_occ.get((classe_id, disciplina))
        if not occ:
            continue

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
        else:
            deficit = slack_deficit(ctx.model, n_blocchi, n_blocchi_min)
            penalita.append(vincolo['peso'] * deficit)

    return penalita
