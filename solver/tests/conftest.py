import os
import sys

sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))


def slots(giorni_ordini):
    """giorni_ordini: dict {giorno: n_slot}. Ritorna lista slot con id progressivi."""
    risultato = []
    sid = 1
    for giorno, n in giorni_ordini.items():
        for ordine in range(1, n + 1):
            risultato.append({'id': sid, 'giorno': giorno, 'ordine': ordine, 'intervallo_dopo': False})
            sid += 1
    return risultato


def problema_base(n_slot_giorno1=4, extra_slots=None, docente_indisponibili=None):
    """Scuola minima: una classe, un giorno da n_slot_giorno1 slot (più eventuali
    slot extra), un docente senza indisponibilità salvo specificato."""
    tutti_slot = slots({1: n_slot_giorno1})
    if extra_slots:
        base_id = len(tutti_slot) + 1
        for giorno, n in extra_slots.items():
            for ordine in range(1, n + 1):
                tutti_slot.append({'id': base_id, 'giorno': giorno, 'ordine': ordine, 'intervallo_dopo': False})
                base_id += 1

    slot_ids = [s['id'] for s in tutti_slot]

    return {
        'seed': 1,
        'time_limit_s': 10,
        'slots': tutti_slot,
        'aule': [],
        'docenti': [{'id': 1, 'indisponibili': docente_indisponibili or []}],
        'classi': [{'id': 1, 'slots_attivi': slot_ids}],
        'lezioni': [],
        'sostegno': [],
        'vincoli': [],
    }


def aggiungi_lezioni(problema, disciplina, ore, docente_id=1, classe_id=1, durata=1, tipo_aula=None, bloccata_slot=None):
    """Aggiunge `ore` lezioni atomiche (durata slot ciascuna) di `disciplina`."""
    prossimo_id = len(problema['lezioni']) + 1
    for i in range(ore):
        problema['lezioni'].append({
            'id': prossimo_id + i,
            'classi': [classe_id],
            'docenti': [docente_id],
            'disciplina': disciplina,
            'durata': durata,
            'tipo_aula': tipo_aula,
            'gruppo_parallelo': None,
            'bloccata_slot': bloccata_slot if i == 0 else None,
        })
    return problema


def completa_slot_rimanenti(problema, disciplina_riempimento='RIEMPI'):
    """Aggiunge lezioni di riempimento così che ogni slot attivo della classe
    sia coperto esattamente una volta (richiesto da H1+H5)."""
    slot_totali = len(problema['classi'][0]['slots_attivi'])
    slot_usati = sum(l['durata'] for l in problema['lezioni'])
    mancanti = slot_totali - slot_usati
    if mancanti > 0:
        aggiungi_lezioni(problema, disciplina_riempimento, mancanti)
    return problema
