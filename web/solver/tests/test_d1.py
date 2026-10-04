import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from conftest import aggiungi_lezioni, completa_slot_rimanenti, problema_base
from solver import risolvi


def _vincolo_d1(min_consecutive=2, n_blocchi_min=1, severita='rigido', peso=None):
    return {
        'id': 1,
        'tipo': 'D1_BLOCCO_MIN_CONSECUTIVO',
        'ambito': {'livello': 'classe', 'ids': [1]},
        'parametri': {'disciplina': 'ITA', 'min_consecutive': min_consecutive, 'n_blocchi_min': n_blocchi_min},
        'severita': severita,
        'peso': peso,
        'attivo': True,
    }


def test_d1_fattibile_con_slot_sufficienti():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 2)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d1()]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')


def test_d1_infattibile_se_la_disciplina_ha_una_sola_ora():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 1)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d1()]

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_d1_preferenziale_non_blocca_ma_penalizza():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 1)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d1(severita='preferenziale', peso=10)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] > 0
