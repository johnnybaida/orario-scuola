"""D3_MAX_ORE_GIORNO — al massimo `max` ore della disciplina per giorno,
per le classi indicate."""

from constraints.util import discipline_del_vincolo, slack_eccesso


def _classi_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'classe':
        return ambito['ids']
    return list(ctx.classi_slot_attivi.keys())


def applica(ctx, vincolo):
    penalita = []
    for disciplina in discipline_del_vincolo(vincolo['parametri']):   # la regola vale per ciascuna disciplina
        penalita += _applica_a(ctx, vincolo, disciplina)
    return penalita


def _applica_a(ctx, vincolo, disciplina):
    massimo = vincolo['parametri']['max']

    penalita = []
    for classe_id in _classi_target(ctx, vincolo):
        occ = ctx.disc_occ.get((classe_id, disciplina))
        if not occ:
            continue

        for giorno in ctx.giorni:
            slot_giorno = ctx.slots_by_day[giorno]
            lits = [occ[s] for s in slot_giorno if s in occ]
            if not lits:
                continue
            totale = sum(lits)

            if vincolo['severita'] == 'rigido':
                ctx.model.Add(totale <= massimo)
            else:
                eccesso = slack_eccesso(ctx.model, totale, massimo)
                penalita.append(vincolo['peso'] * eccesso)

    return penalita
