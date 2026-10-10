"""T11_ORE_IN_FASCIA — ogni docente dell'ambito deve fare almeno `min_ore` ore tra gli slot `slot_ids` (qualunque classe e disciplina; contano le
lezioni e le presenze di sostegno). Rigido = divieto di avere meno ore; preferenziale = ogni ora mancante costa `peso`. Solo ambito docente.
"""

from constraints.util import slack_deficit


def applica(ctx, vincolo):
    minimo = vincolo['parametri']['min_ore']
    slot = vincolo['parametri']['slot_ids']
    penalita = []

    for docente_id in vincolo['ambito']['ids']:
        ore = [ctx.docente_occ.get(docente_id, {}).get(s) for s in slot]
        ore = [v for v in ore if v is not None]
        ore += [v for s in slot for v in getattr(ctx, 'sostegno_docente', {}).get((docente_id, s), [])]
        espressione = sum(ore) if ore else 0
        if vincolo['severita'] == 'rigido':
            ctx.model.Add(espressione >= minimo)
        else:
            penalita.append(vincolo['peso'] * slack_deficit(ctx.model, espressione, minimo))

    return penalita
