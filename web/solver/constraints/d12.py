"""D12_BLOCCO_MAX_CONSECUTIVO — al massimo `max_consecutive` ore consecutive nello stesso giorno. Con ambito classe/globale conta
le ore della `disciplina` in ciascuna classe; con ambito docente conta le lezioni del docente (della `disciplina` se indicata,
altrimenti tutte, in qualunque classe: è il «max ore consecutive» di un docente).

Un blocco è una fila di slot consecutivi dello stesso giorno (le pause non lo interrompono, come in D1). Per ogni finestra di
`max_consecutive + 1` slot consecutivi: rigido = non possono essere tutti occupati; preferenziale = ogni finestra tutta occupata
costa `peso` (un blocco più lungo costa di più).
"""

from constraints.d1 import _classi_target, _occupazione_docente
from constraints.util import discipline_del_vincolo, reify_and


def _finestre_piene(ctx, vincolo, occ, massimo):
    penalita = []
    lunghezza = massimo + 1
    for giorno in ctx.giorni:
        slot_giorno = ctx.slots_by_day[giorno]
        for i in range(len(slot_giorno) - lunghezza + 1):
            finestra = slot_giorno[i:i + lunghezza]
            lits = [occ[s] for s in finestra if s in occ]
            if len(lits) != len(finestra):
                continue   # uno slot non può ospitare la disciplina: la finestra non è mai tutta occupata
            if vincolo['severita'] == 'rigido':
                ctx.model.AddBoolOr([lit.Not() for lit in lits])
            else:
                penalita.append(vincolo['peso'] * reify_and(ctx.model, lits))
    return penalita


def applica(ctx, vincolo):
    elenco = discipline_del_vincolo(vincolo['parametri'])
    penalita = []
    if vincolo['ambito']['livello'] == 'docente':
        for disciplina in elenco or [None]:   # senza discipline contano tutte le lezioni del docente
            penalita += _applica_a(ctx, vincolo, disciplina)
        return penalita

    for disciplina in elenco:   # per classi e globale la disciplina è obbligatoria (la valida il PHP); la regola vale per ciascuna
        penalita += _applica_a(ctx, vincolo, disciplina)
    return penalita


def _applica_a(ctx, vincolo, disciplina):
    massimo = vincolo['parametri']['max_consecutive']

    penalita = []
    if vincolo['ambito']['livello'] == 'docente':
        for docente_id in vincolo['ambito']['ids']:
            occ = _occupazione_docente(ctx, docente_id, disciplina)
            if occ:
                penalita += _finestre_piene(ctx, vincolo, occ, massimo)
        return penalita

    for classe_id in _classi_target(ctx, vincolo):
        occ = ctx.disc_occ.get((classe_id, disciplina))
        if occ:
            penalita += _finestre_piene(ctx, vincolo, occ, massimo)

    return penalita
