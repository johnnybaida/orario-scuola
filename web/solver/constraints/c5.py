"""C5_SPOSTAMENTI_PIANO — limita i cambi di piano di una classe tra due ore
consecutive dello stesso giorno (utile con la DADA, dove sono gli alunni a
spostarsi). Il piano di una lezione è quello dell'aula assegnata (se l'aula ha
un piano), altrimenti quello della classe; una lezione senza piano non conta e
le coppie di ore in cui una delle due non ha piano sono ignorate.
parametri: {soglia?} piani di differenza senza penalità (default 0)."""

from collections import defaultdict

from constraints.util import reify_and


def _classi_target(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'classe':
        return ambito['ids']
    return list(ctx.classi_slot_attivi.keys())


def _posizioni(ctx, classe_id):
    """slot -> (piano, noto): `piano` è il piano della lezione della classe in quello slot (somma di
    termini a una sola variabile attiva, per H1) e `noto` è vero se quel piano esiste."""
    cache = ctx.__dict__.setdefault('cache_piano', {})
    if classe_id in cache:
        return cache[classe_id]

    piano_classe = next((c.get('piano') for c in ctx.problema['classi'] if c['id'] == classe_id), None)
    aula_scelta = getattr(ctx, 'aula_scelta', {})
    termini = defaultdict(list)   # slot -> [(piano, letterale vero se la lezione è lì con quel piano)]

    for lez in ctx.lezioni:
        if classe_id not in lez['classi']:
            continue
        for slot, occ in ctx.occupato[lez['id']].items():
            if lez['id'] in aula_scelta:
                for aula_id, scelta in aula_scelta[lez['id']].items():
                    piano = ctx.aule[aula_id].get('piano')
                    piano = piano_classe if piano is None else piano
                    if piano is not None:
                        termini[slot].append((piano, reify_and(ctx.model, [occ, scelta])))
            elif piano_classe is not None:
                termini[slot].append((piano_classe, occ))

    risultato = {}
    for slot, voci in termini.items():
        noto = ctx.model.NewBoolVar(f'pianonoto_C{classe_id}_S{slot}')
        ctx.model.Add(noto == sum(v for _, v in voci))
        risultato[slot] = (sum(p * v for p, v in voci), noto)
    cache[classe_id] = risultato
    return risultato


def applica(ctx, vincolo):
    soglia = vincolo['parametri'].get('soglia') or 0

    penalita = []
    for classe_id in _classi_target(ctx, vincolo):
        posizioni = _posizioni(ctx, classe_id)
        for giorno in ctx.giorni:
            slot_giorno = ctx.slots_by_day[giorno]
            for prima, dopo in zip(slot_giorno, slot_giorno[1:]):
                if prima not in posizioni or dopo not in posizioni:
                    continue
                (piano_a, noto_a), (piano_b, noto_b) = posizioni[prima], posizioni[dopo]
                entrambi = reify_and(ctx.model, [noto_a, noto_b])
                differenza = piano_a - piano_b

                if vincolo['severita'] == 'rigido':
                    ctx.model.Add(differenza <= soglia).OnlyEnforceIf(entrambi)
                    ctx.model.Add(-differenza <= soglia).OnlyEnforceIf(entrambi)
                else:
                    eccesso = ctx.model.NewIntVar(0, 30, f'salto_C{classe_id}_S{prima}')
                    ctx.model.Add(eccesso >= differenza - soglia).OnlyEnforceIf(entrambi)
                    ctx.model.Add(eccesso >= -differenza - soglia).OnlyEnforceIf(entrambi)
                    penalita.append(vincolo['peso'] * eccesso)

    return penalita
