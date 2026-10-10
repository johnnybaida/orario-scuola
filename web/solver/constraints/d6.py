"""D6_FASCIA_ORARIA — la disciplina è vietata o preferita in un insieme di
slot, per le classi indicate. parametri: {disciplina, tipo: 'vietata'|
'preferita', slot_ids: [...]}."""


def _classi_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'classe':
        return ambito['ids']
    return list(ctx.classi_slot_attivi.keys())


def applica(ctx, vincolo):
    from constraints.util import discipline_del_vincolo

    penalita = []
    for disciplina in discipline_del_vincolo(vincolo['parametri']):   # la regola vale per ciascuna disciplina
        penalita += _applica_a(ctx, vincolo, disciplina)
    return penalita


def _applica_a(ctx, vincolo, disciplina):
    parametri = vincolo['parametri']
    tipo = parametri['tipo']
    slot_ids = set(parametri['slot_ids'])

    penalita = []
    for classe_id in _classi_target(ctx, vincolo):
        occ = ctx.disc_occ.get((classe_id, disciplina))
        if not occ:
            continue

        if tipo == 'vietata':
            lits_vietati = [occ[s] for s in slot_ids if s in occ]
            if not lits_vietati:
                continue
            if vincolo['severita'] == 'rigido':
                for lit in lits_vietati:
                    ctx.model.Add(lit == 0)
            else:
                penalita.append(vincolo['peso'] * sum(lits_vietati))
        else:  # preferita
            lits_fuori = [v for s, v in occ.items() if s not in slot_ids]
            if not lits_fuori:
                continue
            if vincolo['severita'] == 'rigido':
                for lit in lits_fuori:
                    ctx.model.Add(lit == 0)
            else:
                penalita.append(vincolo['peso'] * sum(lits_fuori))

    return penalita
