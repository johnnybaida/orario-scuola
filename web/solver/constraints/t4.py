"""T4_ORE_GIORNO — ore minime e/o massime al giorno di un docente (ambito docente o globale = tutti i docenti con ore). Contano le lezioni e le
presenze di sostegno. Le indisponibilità si rispettano: un giorno in cui il docente non può esserci in nessuna ora non conta (nessun minimo).

`min_ore`: il minimo vale in ogni giorno in cui il docente può esserci. Rigido: se le sue ore totali sono meno di (min × giorni utili) il minimo si
riduce a ore // giorni utili, per non rendere impossibile il vincolo per aritmetica; preferenziale: ogni ora sotto il minimo in un giorno utile costa
`peso` (così le ore si distribuiscono su più giorni possibile).
`max_ore`: tetto giornaliero; rigido = divieto, preferenziale = ogni ora oltre il tetto costa `peso`.
"""

from constraints.util import _contatore


def _eccesso(model, espressione, soglia, n_termini):
    """IntVar >= max(0, espressione - soglia), con dominio stretto (al più i termini della somma): propaga meglio di un limite generico."""
    v = model.NewIntVar(0, max(0, n_termini - soglia), f't4_ecc_{next(_contatore)}')
    model.Add(v >= espressione - soglia)
    return v


def _deficit(model, espressione, soglia):
    v = model.NewIntVar(0, soglia, f't4_def_{next(_contatore)}')
    model.Add(v >= soglia - espressione)
    return v


def _docenti(ctx, vincolo):
    ambito = vincolo['ambito']
    if ambito['livello'] == 'docente':
        return ambito['ids']
    return sorted(set(ctx.docente_occ.keys()) | set(getattr(ctx, 'sostegno_ore_docente', {}).keys()))


def applica(ctx, vincolo):
    minimo = vincolo['parametri'].get('min_ore')
    massimo = vincolo['parametri'].get('max_ore')
    rigido = vincolo['severita'] == 'rigido'
    penalita = []

    for docente_id in _docenti(ctx, vincolo):
        per_slot = ctx.docente_occ.get(docente_id, {})
        indisponibili = ctx.docenti_indisponibili.get(docente_id, set())
        sostegno = {s: lits for (d, s), lits in getattr(ctx, 'sostegno_docente', {}).items() if d == docente_id}

        ore_giorno = {}   # giorno utile -> (espressione delle ore del docente quel giorno, numero di termini)
        for giorno in ctx.giorni:
            slot = [s for s in ctx.slots_by_day[giorno] if s not in indisponibili and (s in per_slot or s in sostegno)]
            if slot:
                termini = [per_slot[s] for s in slot if s in per_slot] + [v for s in slot for v in sostegno.get(s, [])]
                ore_giorno[giorno] = (sum(termini), len(termini))
        if not ore_giorno:
            continue

        if minimo:
            totale = sum(l['durata'] for l in ctx.lezioni if docente_id in l['docenti']) + getattr(ctx, 'sostegno_ore_docente', {}).get(docente_id, 0)
            effettivo = min(minimo, totale // len(ore_giorno))
            for espressione, _ in ore_giorno.values():
                if rigido:
                    if effettivo:
                        ctx.model.Add(espressione >= effettivo)
                else:
                    penalita.append(vincolo['peso'] * _deficit(ctx.model, espressione, minimo))
        if massimo:
            for espressione, n_termini in ore_giorno.values():
                if rigido:
                    ctx.model.Add(espressione <= massimo)
                elif n_termini > massimo:   # con meno termini del tetto il tetto non si può superare
                    penalita.append(vincolo['peso'] * _eccesso(ctx.model, espressione, massimo, n_termini))

    return penalita
