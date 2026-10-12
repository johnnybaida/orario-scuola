"""S1_DISCIPLINE_PREFERITE e S2_DISCIPLINE_ESCLUSE — in quali lezioni sta il docente di sostegno (compresenza).

S1: il sostegno sta solo nelle lezioni delle `discipline` (ogni presenza fuori da esse è una violazione).
S2: il sostegno non sta nelle lezioni delle `discipline` (ogni presenza in esse è una violazione).
Rigido = divieto; preferenziale = ogni presenza in violazione costa `peso`. Ambito globale, classe o docente (di sostegno).
"""

from constraints.d1 import _classi_target
from constraints.util import discipline_del_vincolo, reify_and, reify_or


def applica(ctx, vincolo):
    discipline = discipline_del_vincolo(vincolo['parametri'])
    preferite = vincolo['tipo'] == 'S1_DISCIPLINE_PREFERITE'
    docenti = set(vincolo['ambito']['ids']) if vincolo['ambito']['livello'] == 'docente' else None
    penalita = []

    for classe_id in _classi_target(ctx, vincolo):
        dati = getattr(ctx, 'sostegno_presenze', {}).get(classe_id)
        if not dati:
            continue
        for docente_id, per_slot in dati['docenti'].items():
            if docenti is not None and docente_id not in docenti:
                continue
            for s, presente in per_slot.items():
                lits = [ctx.disc_occ[(classe_id, d)][s] for d in discipline if s in ctx.disc_occ.get((classe_id, d), {})]
                in_disciplina = reify_or(ctx.model, lits)   # nessuna lezione di quelle discipline in quell'ora: falso
                # presenza in violazione: S1 fuori dalle discipline, S2 dentro
                cattiva = in_disciplina.Not() if preferite else in_disciplina
                if vincolo['severita'] == 'rigido':
                    ctx.model.AddBoolOr([presente.Not(), cattiva.Not()])
                else:
                    penalita.append(vincolo['peso'] * reify_and(ctx.model, [presente, cattiva]))

    return penalita
