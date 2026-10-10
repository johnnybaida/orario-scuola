"""D13_DISCIPLINA_SEGUITA — dopo una lezione delle discipline di partenza, nell'ora subito successiva dello stesso giorno (le pause non interrompono)
c'è (o non c'è) una lezione delle discipline `discipline_seguite`. Per ciascuna classe dell'ambito (classe o globale).

Le discipline possono essere limitate alle sole lezioni con o senza compresenza CLIL (`clil_prima`, `clil_dopo`: tutte|con|senza);
`inverso` (solo non_segue) vale anche per la coppia in ordine inverso.

modo `segue`: senza `min_coppie` ogni lezione di partenza deve avere la successiva (anche l'ultima ora del giorno è una violazione); rigido = divieto,
preferenziale = ogni lezione senza la successiva costa `peso`. Con `min_coppie` servono almeno quelle coppie nella settimana (rigido = divieto,
preferenziale = ogni coppia mancante costa `peso`).
modo `non_segue`: rigido = nessuna coppia; preferenziale = ogni coppia costa `peso`.
"""

from constraints.d1 import _classi_target
from constraints.util import reify_and, reify_or, slack_deficit


def _unione(ctx, classe_id, codici, clil=None):
    """slot -> letterale vero se in quello slot la classe ha una lezione di una delle discipline (clil: None = qualsiasi, True = solo con CLIL, False = solo senza)."""
    per_slot = {}
    for lez in ctx.lezioni:
        if classe_id in lez['classi'] and lez['disciplina'] in codici and (clil is None or bool(lez.get('clil')) == clil):
            for s, v in ctx.occupato[lez['id']].items():
                per_slot.setdefault(s, []).append(v)
    return {s: (lits[0] if len(lits) == 1 else reify_or(ctx.model, lits)) for s, lits in per_slot.items()}


def _clil(valore):
    return {'con': True, 'senza': False}.get(valore)


def applica(ctx, vincolo):
    p = vincolo['parametri']
    partenza = p.get('discipline') or ([p['disciplina']] if p.get('disciplina') else [])
    seguite = p.get('discipline_seguite', [])
    modo = p.get('modo', 'segue')
    minimo = p.get('min_coppie')
    rigido = vincolo['severita'] == 'rigido'
    penalita = []

    for classe_id in _classi_target(ctx, vincolo):
        prima = _unione(ctx, classe_id, partenza, _clil(p.get('clil_prima')))
        dopo = _unione(ctx, classe_id, seguite, _clil(p.get('clil_dopo')))
        direzioni = [(prima, dopo)]
        if modo == 'non_segue' and p.get('inverso'):
            direzioni.append((dopo, prima))   # anche nell'ordine inverso (es. mai GEO e GEO con CLIL di seguito, in nessun ordine)
        coppie = []   # (letterale prima, letterale dopo o None se non esiste lo slot successivo)
        for davanti, dietro in direzioni:
            for giorno in ctx.giorni:
                slot_giorno = ctx.slots_by_day[giorno]
                for i, s in enumerate(slot_giorno):
                    if s in davanti:
                        successivo = slot_giorno[i + 1] if i + 1 < len(slot_giorno) else None
                        coppie.append((davanti[s], dietro.get(successivo) if successivo is not None else None))

        if modo == 'non_segue':
            for a, b in coppie:
                if b is None:
                    continue
                if rigido:
                    ctx.model.AddBoolOr([a.Not(), b.Not()])
                else:
                    penalita.append(vincolo['peso'] * reify_and(ctx.model, [a, b]))
        elif minimo:
            vere = [reify_and(ctx.model, [a, b]) for a, b in coppie if b is not None]
            totale = sum(vere) if vere else 0
            if rigido:
                ctx.model.Add(totale >= minimo)
            else:
                penalita.append(vincolo['peso'] * slack_deficit(ctx.model, totale, minimo))
        else:
            for a, b in coppie:
                if rigido:
                    if b is None:
                        ctx.model.Add(a == 0)
                    else:
                        ctx.model.AddImplication(a, b)
                else:
                    manca = ctx.model.NewBoolVar(f'd13_manca_C{classe_id}')
                    ctx.model.AddBoolOr([a.Not()] + ([b] if b is not None else []) + [manca])
                    penalita.append(vincolo['peso'] * manca)

    return penalita
