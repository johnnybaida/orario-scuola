"""Spiegazione dell'infattibilità: quando i vincoli rigidi non possono valere tutti insieme, cerca un sottoinsieme minimo che resta impossibile
(«deletion filter»: si prova a togliere un vincolo alla volta e lo si tiene fuori se il problema resta impossibile) e, per i vincoli su più docenti o
classi, restringe l'elenco a quelli che causano il conflitto. Ogni prova è una risoluzione completa con un limite di tempo breve; tutta l'analisi sta nel
budget `diagnosi_s` del problema (0 = disattivata). Le righe prodotte sono lette da DiagnosticaSolver (PHP):

  «Conflitto tra vincoli: 5, 13, 22»                         id dei vincoli del nucleo
  «Vincolo 22 ristretto a docenti: 41, 52»                   (o «classi») sotto-elenco dell'ambito che basta a rendere impossibile il problema
  «Anche senza vincoli configurati …»                        l'impossibilità viene dai dati (cattedre, indisponibilità, slot, aule)
  «Analisi interrotta …»                                     tempo finito: il risultato è parziale
"""

import copy
import time

LIMITE_PROVA_S = 20


def _prova(problema, vincoli, limite):
    """'infattibile' | 'fattibile' | 'incerto' (tempo scaduto) risolvendo il problema con i soli `vincoli` dati."""
    from solver import risolvi   # import tardivo: solver.py importa questo modulo

    p = copy.deepcopy(problema)
    p['vincoli'] = vincoli
    p['time_limit_s'] = max(1, int(limite))
    p['diagnosi_s'] = 0
    stato = risolvi(p)['stato']
    return {'infattibile': 'infattibile', 'timeout': 'incerto'}.get(stato, 'fattibile')


def spiega(problema):
    budget = float(problema.get('diagnosi_s', 0) or 0)
    if budget <= 0:
        return []
    fine = time.monotonic() + budget

    def resto():
        return fine - time.monotonic()

    def prova(vincoli):
        return _prova(problema, vincoli, min(LIMITE_PROVA_S, resto())) if resto() > 1 else 'incerto'

    rigidi = [v for v in problema.get('vincoli', []) if v.get('severita') == 'rigido' and v.get('attivo', True)]
    if not rigidi:
        return []
    if prova([]) == 'infattibile':
        return ["Anche senza i vincoli configurati l'orario è impossibile: il problema è nei dati (cattedre, indisponibilità dei docenti, slot attivi, aule)."]

    nucleo = list(rigidi)
    interrotta = False
    for v in list(nucleo):
        if resto() <= 1:
            interrotta = True
            break
        senza = [x for x in nucleo if x is not v]
        if prova(senza) == 'infattibile':   # senza v il problema è comunque impossibile: v non serve a spiegarlo
            nucleo = senza

    righe = []
    if len(nucleo) == len(rigidi) and interrotta:
        return ["Analisi dell'infattibilità interrotta per il tempo: non è stato possibile isolare i vincoli in conflitto."]
    righe.append('Conflitto tra vincoli: ' + ', '.join(str(v.get('id')) for v in nucleo))

    # Restringe i vincoli con ambito su più docenti/classi a quelli che bastano a rendere impossibile il problema.
    for v in nucleo:
        ambito = v.get('ambito', {})
        ids = list(ambito.get('ids') or [])
        if ambito.get('livello') not in ('docente', 'classe') or len(ids) < 2:
            continue
        corrente = ids
        while len(corrente) > 1 and resto() > 1:
            meta = corrente[:len(corrente) // 2]
            altra = corrente[len(corrente) // 2:]
            for parte in (meta, altra):
                ristretto = copy.deepcopy(v)
                ristretto['ambito']['ids'] = parte
                if prova([x if x is not v else ristretto for x in nucleo]) == 'infattibile':
                    corrente = parte
                    break
            else:
                break   # nessuna metà basta da sola: il conflitto nasce dall'insieme
        if len(corrente) < len(ids):
            righe.append(f"Vincolo {v.get('id')} ristretto a {'docenti' if ambito['livello'] == 'docente' else 'classi'}: " + ', '.join(str(i) for i in corrente))
    if interrotta or resto() <= 1:
        righe.append("Analisi dell'infattibilità interrotta per il tempo: l'elenco potrebbe contenere più vincoli del necessario.")

    return righe
