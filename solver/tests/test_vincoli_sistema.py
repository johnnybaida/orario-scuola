from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def test_h6_indisponibilita_docente_esclude_lo_slot():
    problema = problema_base(n_slot_giorno1=4, docente_indisponibili=[1])
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 3, docente_id=1)
    aggiungi_lezioni(problema, 'RIEMPI', 1, docente_id=2)

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    lezioni_docente1 = [l['id'] for l in problema['lezioni'] if l['docenti'] == [1]]
    assert all(a['slot'] != 1 for a in risultato['assegnazioni'] if a['lezione'] in lezioni_docente1)


def test_h6_infattibile_se_lindisponibilita_non_lascia_slot_sufficienti():
    problema = problema_base(n_slot_giorno1=1, docente_indisponibili=[1])
    aggiungi_lezioni(problema, 'ITA', 1)

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_h3_h7_rispetta_capienza_e_tipo_aula():
    problema = problema_base(n_slot_giorno1=2)
    problema['aule'] = [{'id': 1, 'tipo': 'palestra', 'capacita': 1}]
    problema['classi'].append({'id': 2, 'slots_attivi': problema['classi'][0]['slots_attivi']})
    problema['docenti'].append({'id': 2, 'indisponibili': []})

    aggiungi_lezioni(problema, 'MOT', 1, classe_id=1, docente_id=1, tipo_aula='palestra', bloccata_slot=1)
    aggiungi_lezioni(problema, 'MOT', 1, classe_id=2, docente_id=2, tipo_aula='palestra', bloccata_slot=1)
    aggiungi_lezioni(problema, 'RIEMPI', 1, classe_id=1, docente_id=1)  # riempie lo slot 2 della classe 1
    aggiungi_lezioni(problema, 'RIEMPI', 1, classe_id=2, docente_id=2)  # riempie lo slot 2 della classe 2

    risultato = risolvi(problema)

    # le due lezioni di motoria sono forzate nello stesso slot ma la palestra
    # ha capienza 1: non può esistere una soluzione valida.
    assert risultato['stato'] == 'infattibile'
