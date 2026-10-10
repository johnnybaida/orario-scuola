"""S5_DISTRIBUZIONE_SOSTEGNO — il sostegno di una classe non si concentra.

`max_insieme`: al massimo tanti docenti di sostegno insieme nella stessa classe e ora (rigido = divieto; preferenziale = ogni docente oltre
il massimo costa `peso`). `tolleranza_giorno`: le ore di sostegno della classe si distribuiscono sui giorni in cui almeno un docente può
esserci (le indisponibilità restano fuori: quei giorni non contano); il tetto di un giorno è ceil(ore / giorni utili) + tolleranza
(rigido = divieto di superarlo; preferenziale = ogni ora oltre il tetto costa `peso`). Ambito globale o classe.
"""

import math

from constraints.d1 import _classi_target
from constraints.util import slack_eccesso


def _vincola(ctx, vincolo, espressione, soglia, penalita):
    if vincolo['severita'] == 'rigido':
        ctx.model.Add(espressione <= soglia)
    else:
        penalita.append(vincolo['peso'] * slack_eccesso(ctx.model, espressione, soglia))


def applica(ctx, vincolo):
    parametri = vincolo['parametri']
    max_insieme = parametri.get('max_insieme')
    tolleranza = parametri.get('tolleranza_giorno')
    penalita = []

    for classe_id in _classi_target(ctx, vincolo):
        dati = getattr(ctx, 'sostegno_presenze', {}).get(classe_id)
        if not dati:
            continue

        if max_insieme:
            for lits in dati['slot'].values():
                if len(lits) > max_insieme:
                    _vincola(ctx, vincolo, sum(lits), max_insieme, penalita)

        if tolleranza is not None:
            per_giorno = {g: [l for s in ctx.slots_by_day[g] for l in dati['slot'].get(s, [])] for g in ctx.giorni}
            giorni_utili = [g for g, lits in per_giorno.items() if lits]
            if giorni_utili:
                tetto = math.ceil(dati['ore'] / len(giorni_utili)) + tolleranza
                for g in giorni_utili:
                    _vincola(ctx, vincolo, sum(per_giorno[g]), tetto, penalita)

    return penalita
