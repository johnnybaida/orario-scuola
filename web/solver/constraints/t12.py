"""T12_ORE_ATTORNO_MENSA — nei giorni in cui un docente sorveglia la mensa (`assistenze_mensa`: docente, giorno, ordine dell'ora che precede la
pausa) deve avere almeno `min_ore` ore (lezioni + sostegno) secondo `modo`: prima | dopo | prima_o_dopo (somma delle due parti) | a_cavallo (almeno
`min_ore` sia prima sia dopo). Ambito globale (tutti i sorveglianti) o docente. Rigido = divieto; preferenziale = ogni ora mancante costa `peso`.
"""

from constraints.util import slack_deficit


def _ore(ctx, docente_id, slot):
    termini = [ctx.docente_occ.get(docente_id, {}).get(s) for s in slot]
    termini = [v for v in termini if v is not None]
    termini += [v for s in slot for v in getattr(ctx, 'sostegno_docente', {}).get((docente_id, s), [])]
    return sum(termini) if termini else 0


def applica(ctx, vincolo):
    minimo = vincolo['parametri']['min_ore']
    modo = vincolo['parametri'].get('modo', 'prima_o_dopo')
    ids = vincolo['ambito']['ids'] if vincolo['ambito']['livello'] == 'docente' else None
    penalita = []

    def richiedi(espressione):
        if vincolo['severita'] == 'rigido':
            ctx.model.Add(espressione >= minimo)
        else:
            penalita.append(vincolo['peso'] * slack_deficit(ctx.model, espressione, minimo))

    for a in ctx.problema.get('assistenze_mensa', []):
        if ids is not None and a['docente'] not in ids:
            continue
        giorno = ctx.slots_by_day.get(a['giorno'], [])
        prima = _ore(ctx, a['docente'], [s for s in giorno if ctx.slots[s]['ordine'] <= a['ordine']])
        dopo = _ore(ctx, a['docente'], [s for s in giorno if ctx.slots[s]['ordine'] > a['ordine']])
        if modo == 'prima':
            richiedi(prima)
        elif modo == 'dopo':
            richiedi(dopo)
        elif modo == 'a_cavallo':
            richiedi(prima)
            richiedi(dopo)
        else:
            richiedi(prima + dopo)

    return penalita
