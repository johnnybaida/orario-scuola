#!/usr/bin/env python3
"""Entrypoint del solver CP-SAT: legge il problema JSON da stdin, scrive la
soluzione JSON su stdout. Contratto descritto in CLAUDE.md. Codice di uscita
diverso da zero solo per errori tecnici; l'infattibilità è un esito valido
restituito nel JSON (stato: 'infattibile')."""

import json
import sys
import time

from ortools.sat.python import cp_model

import diagnosi
import sostegno as sostegno_modulo
from contesto import Contesto
from constraints import c5, d1, d3, d6, d12, d13, s5, t11, t2, t3, t4

MODULI_VINCOLI = {
    'D1_BLOCCO_MIN_CONSECUTIVO': d1,
    'D3_MAX_ORE_GIORNO': d3,
    'D6_FASCIA_ORARIA': d6,
    'D12_BLOCCO_MAX_CONSECUTIVO': d12,
    'D13_DISCIPLINA_SEGUITA': d13,
    'T11_ORE_IN_FASCIA': t11,
    'T4_ORE_GIORNO': t4,
    'T2_GIORNO_LIBERO': t2,
    'T3_MAX_ORE_BUCHE': t3,
    'C5_SPOSTAMENTI_PIANO': c5,
    'S5_DISTRIBUZIONE_SOSTEGNO': s5,
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

    limite = problema.get('time_limit_s', 120)
    seme = problema.get('seed', 0) % 2147483647   # random_seed è un int32 lato OR-Tools

    def nuovo_solver(secondi):
        s = cp_model.CpSolver()
        s.parameters.max_time_in_seconds = max(1, secondi)
        s.parameters.random_seed = seme
        s.parameters.num_search_workers = 1
        return s

    solver = None
    stato = None
    if penalita_totali:
        # Prima si cerca una soluzione qualsiasi, senza obiettivo (con molti vincoli preferenziali la ricerca con l'obiettivo può non trovarne nessuna
        # nel tempo); poi la si migliora minimizzando le penalità, partendo da quella soluzione come suggerimento.
        fase1 = nuovo_solver(min(limite * 0.4, 150))
        inizio = time.monotonic()
        stato1 = fase1.Solve(model)
        if stato1 in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            model.ClearHints()
            for i in range(len(model.Proto().variables)):
                var = model.GetIntVarFromProtoIndex(i)
                model.AddHint(var, fase1.Value(var))
            model.Minimize(sum(penalita_totali))
            solver = nuovo_solver(limite - (time.monotonic() - inizio))
            stato = solver.Solve(model)
            if stato not in (cp_model.OPTIMAL, cp_model.FEASIBLE):   # non dovrebbe succedere: si tiene la soluzione della prima fase
                solver, stato = fase1, cp_model.FEASIBLE
        elif stato1 in (cp_model.INFEASIBLE, cp_model.MODEL_INVALID):
            solver, stato = fase1, stato1
        else:
            model.Minimize(sum(penalita_totali))   # nessuna soluzione trovata nella prima fase: si prosegue con l'obiettivo per il tempo che resta
            solver = nuovo_solver(limite - (time.monotonic() - inizio))
            stato = solver.Solve(model)
    else:
        solver = nuovo_solver(limite)
        stato = solver.Solve(model)

    if stato in (cp_model.INFEASIBLE, cp_model.MODEL_INVALID):
        return {
            'stato': 'infattibile',
            'punteggio': None,
            'assegnazioni': [],
            'compresenze_sostegno': [],
            'violazioni_soft': [],
            'diagnostica': ['Nessuna soluzione soddisfa i vincoli rigidi con i dati forniti.'] + diagnosi.spiega(problema),
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
