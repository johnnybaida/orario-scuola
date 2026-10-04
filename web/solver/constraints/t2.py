"""T2_GIORNO_LIBERO — il docente deve avere almeno n_giorni senza lezioni.
parametri: {n_giorni, preferenze: [giorno,...]} (preferenze opzionale, usata
solo come lieve bonus in modalità preferenziale)."""

from constraints.util import reify_or, slack_deficit


def _docenti_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'docente':
        return ambito['ids']
    return list(ctx.docente_occ.keys())


def applica(ctx, vincolo):
    parametri = vincolo['parametri']
    n_giorni = parametri['n_giorni']
    preferenze = parametri.get('preferenze', [])

    penalita = []
    for docente_id in _docenti_target(ctx, vincolo):
        per_slot = ctx.docente_occ.get(docente_id)
        if not per_slot:
            continue

        giorno_libero = {}
        for giorno in ctx.giorni:
            lits = [per_slot[s] for s in ctx.slots_by_day[giorno] if s in per_slot]
            occupato_giorno = reify_or(ctx.model, lits)
            libero = ctx.model.NewBoolVar(f'giornolibero_D{docente_id}_G{giorno}')
            ctx.model.Add(libero == 1 - occupato_giorno)
            giorno_libero[giorno] = libero

        n_liberi = sum(giorno_libero.values())

        if vincolo['severita'] == 'rigido':
            ctx.model.Add(n_liberi >= n_giorni)
        else:
            deficit = slack_deficit(ctx.model, n_liberi, n_giorni)
            penalita.append(vincolo['peso'] * deficit)

            if preferenze:
                preferito = preferenze[0]
                if preferito in giorno_libero:
                    mancato = ctx.model.NewIntVar(0, 1, f'prefmancata_D{docente_id}')
                    ctx.model.Add(mancato == 1 - giorno_libero[preferito])
                    penalita.append(mancato)

    return penalita
