"""T3_MAX_ORE_BUCHE — limita le ore "buca" (slot libero tra due lezioni dello
stesso giorno) del docente. parametri: {max_per_giorno?, max_per_settimana?}
(almeno uno dei due)."""

from constraints.util import reify_or, slack_eccesso


def _docenti_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'docente':
        return ambito['ids']
    return list(ctx.docente_occ.keys())


def _buche_giorno(ctx, docente_id, giorno, per_slot):
    slot_giorno = ctx.slots_by_day[giorno]
    occupato = [per_slot.get(s, ctx.falso) for s in slot_giorno]

    n = len(slot_giorno)
    prefix_any = [None] * n
    suffix_any = [None] * n
    for i in range(n):
        prefix_any[i] = reify_or(ctx.model, occupato[:i + 1])
    for i in range(n - 1, -1, -1):
        suffix_any[i] = reify_or(ctx.model, occupato[i:])

    buche = []
    for i in range(1, n - 1):
        buca = ctx.model.NewBoolVar(f'buca_D{docente_id}_S{slot_giorno[i]}')
        ctx.model.AddBoolAnd([prefix_any[i - 1], suffix_any[i + 1], occupato[i].Not()]).OnlyEnforceIf(buca)
        ctx.model.AddBoolOr([prefix_any[i - 1].Not(), suffix_any[i + 1].Not(), occupato[i], buca])
        buche.append(buca)
    return buche


def applica(ctx, vincolo):
    parametri = vincolo['parametri']
    max_per_giorno = parametri.get('max_per_giorno')
    max_per_settimana = parametri.get('max_per_settimana')

    penalita = []
    for docente_id in _docenti_target(ctx, vincolo):
        per_slot = ctx.docente_occ.get(docente_id)
        if not per_slot:
            continue

        buche_settimana = []
        for giorno in ctx.giorni:
            buche = _buche_giorno(ctx, docente_id, giorno, per_slot)
            if not buche:
                continue
            totale_giorno = sum(buche)
            buche_settimana.extend(buche)

            if max_per_giorno is not None:
                if vincolo['severita'] == 'rigido':
                    ctx.model.Add(totale_giorno <= max_per_giorno)
                else:
                    eccesso = slack_eccesso(ctx.model, totale_giorno, max_per_giorno)
                    penalita.append(vincolo['peso'] * eccesso)

        if max_per_settimana is not None and buche_settimana:
            totale_settimana = sum(buche_settimana)
            if vincolo['severita'] == 'rigido':
                ctx.model.Add(totale_settimana <= max_per_settimana)
            else:
                eccesso = slack_eccesso(ctx.model, totale_settimana, max_per_settimana)
                penalita.append(vincolo['peso'] * eccesso)

    return penalita
