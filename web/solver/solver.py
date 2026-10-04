#!/usr/bin/env python3
"""Entrypoint del solver CP-SAT: legge il problema JSON da stdin, scrive la
soluzione JSON su stdout. Contratto descritto in CLAUDE.md. Codice di uscita
diverso da zero solo per errori tecnici; l'infattibilità è un esito valido
restituito nel JSON (stato: 'infattibile')."""

import json
import sys

from ortools.sat.python import cp_model

import sostegno as sostegno_modulo
from contesto import Contesto
from constraints import d1, d3, d6, t2, t3

MODULI_VINCOLI = {
    'D1_BLOCCO_MIN_CONSECUTIVO': d1,
    'D3_MAX_ORE_GIORNO': d3,
    'D6_FASCIA_ORARIA': d6,
    'T2_GIORNO_LIBERO': t2,
    'T3_MAX_ORE_BUCHE': t3,
}


def risolvi(problema: dict) -> dict:
    model = cp_model.CpModel()
    ctx = Contesto(model, problema)

    if ctx.diagnostica:
        return {
            'stato': 'infattibile',
            'punteggio': None,
            'assegnazioni': [],
            'compresenze_sostegno': [],
            'violazioni_soft': [],
            'diagnostica': ctx.diagnostica,
        }

    ctx.applica_vincoli_sistema()

    assegnazioni_sostegno, diagnostica_sostegno = sostegno_modulo.applica(ctx, problema.get('sostegno', []))
    if diagnostica_sostegno:
        return {
            'stato': 'infattibile',
            'punteggio': None,
            'assegnazioni': [],
            'compresenze_sostegno': [],
            'violazioni_soft': [],
            'diagnostica': diagnostica_sostegno,
        }

    penalita_totali = []
    violazioni_soft = []
    for vincolo in problema.get('vincoli', []):
        modulo = MODULI_VINCOLI.get(vincolo['tipo'])
        if modulo is None:
            continue
        if not vincolo.get('attivo', True):
            continue
        penalita = modulo.applica(ctx, vincolo) or []
        penalita_totali.extend(penalita)
        if vincolo['severita'] == 'preferenziale' and penalita:
            violazioni_soft.append((vincolo, penalita))

    if penalita_totali:
        model.Minimize(sum(penalita_totali))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = problema.get('time_limit_s', 120)
    # random_seed è un int32 lato OR-Tools: riduco difensivamente qualunque
    # valore più grande arrivi dal chiamante.
    solver.parameters.random_seed = problema.get('seed', 0) % 2147483647
    solver.parameters.num_search_workers = 1

    stato = solver.Solve(model)

    if stato in (cp_model.INFEASIBLE, cp_model.MODEL_INVALID):
        return {
            'stato': 'infattibile',
            'punteggio': None,
            'assegnazioni': [],
            'compresenze_sostegno': [],
            'violazioni_soft': [],
            'diagnostica': ['Nessuna soluzione soddisfa i vincoli rigidi con i dati forniti.'],
        }

    if stato == cp_model.UNKNOWN:
        return {
            'stato': 'timeout',
            'punteggio': None,
            'assegnazioni': [],
            'compresenze_sostegno': [],
            'violazioni_soft': [],
            'diagnostica': ['Tempo limite raggiunto senza trovare una soluzione.'],
        }

    assegnazioni = []
    for lez in ctx.lezioni:
        lid = lez['id']
        slot_scelto = next(s for s, v in ctx.start[lid].items() if solver.Value(v))
        aula_scelta = None
        if lid in ctx.aula_scelta:
            aula_scelta = next(a for a, v in ctx.aula_scelta[lid].items() if solver.Value(v))
        assegnazioni.append({'lezione': lid, 'slot': slot_scelto, 'aula': aula_scelta})

    compresenze_sostegno = [
        {'docente': docente_id, 'classe': classe_id, 'slot': slot_id, 'codice': codice}
        for docente_id, classe_id, slot_id, codice, v in assegnazioni_sostegno
        if solver.Value(v)
    ]

    violazioni_output = []
    for vincolo, penalita in violazioni_soft:
        conteggio = sum(solver.Value(p) for p in penalita)
        if conteggio:
            violazioni_output.append({
                'vincolo_id': vincolo.get('id'),
                'conteggio': conteggio,
                'dettaglio': f"{vincolo['tipo']}: penalità totale {conteggio}",
            })

    return {
        'stato': 'ottimo' if stato == cp_model.OPTIMAL else 'fattibile',
        'punteggio': int(solver.ObjectiveValue()) if penalita_totali else 0,
        'assegnazioni': assegnazioni,
        'compresenze_sostegno': compresenze_sostegno,
        'violazioni_soft': violazioni_output,
        'diagnostica': [],
    }


def main():
    problema = json.load(sys.stdin)
    risultato = risolvi(problema)
    json.dump(risultato, sys.stdout)


if __name__ == '__main__':
    main()
