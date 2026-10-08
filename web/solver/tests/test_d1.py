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


def _vincolo_d1_docente(disciplina=None, severita='rigido'):
    return {
        'id': 1,
        'tipo': 'D1_BLOCCO_MIN_CONSECUTIVO',
        'ambito': {'livello': 'docente', 'ids': [1]},
        'parametri': {'disciplina': disciplina, 'min_consecutive': 2, 'n_blocchi_min': 1},
        'severita': severita,
        'peso': None if severita == 'rigido' else 10,
        'attivo': True,
    }


def _scuola_docente(bloccata_a, bloccata_b):
    """Il docente 1 ha due lezioni (Italiano e Storia, classi diverse) in 4 ore; le altre sono dei docenti 2 e 3."""
    problema = problema_base(n_slot_giorno1=4)
    problema['classi'].append({'id': 2, 'slots_attivi': problema['classi'][0]['slots_attivi']})
    problema['docenti'] += [{'id': 2, 'indisponibili': []}, {'id': 3, 'indisponibili': []}]
    aggiungi_lezioni(problema, 'ITA', 1, classe_id=1, docente_id=1, bloccata_slot=bloccata_a)
    aggiungi_lezioni(problema, 'STO', 1, classe_id=2, docente_id=1, bloccata_slot=bloccata_b)
    aggiungi_lezioni(problema, 'RIEMPI', 3, classe_id=1, docente_id=2)
    aggiungi_lezioni(problema, 'RIEMPI', 3, classe_id=2, docente_id=3)
    return problema


def test_d1_docente_senza_disciplina_conta_le_lezioni_in_classi_diverse():
    problema = _scuola_docente(bloccata_a=1, bloccata_b=2)   # classi e materie diverse, ore 1 e 2 consecutive
    problema['vincoli'] = [_vincolo_d1_docente()]

    assert risolvi(problema)['stato'] in ('ottimo', 'fattibile')


def test_d1_docente_infattibile_se_le_lezioni_del_docente_non_possono_essere_consecutive():
    problema = _scuola_docente(bloccata_a=1, bloccata_b=3)   # ore 1 e 3: mai di fila
    problema['vincoli'] = [_vincolo_d1_docente()]

    assert risolvi(problema)['stato'] == 'infattibile'


def test_d1_docente_con_disciplina_conta_solo_quella_disciplina():
    problema = _scuola_docente(bloccata_a=1, bloccata_b=2)   # consecutive, ma di materie diverse
    problema['vincoli'] = [_vincolo_d1_docente(disciplina='ITA')]

    assert risolvi(problema)['stato'] == 'infattibile'   # di Italiano ha una sola ora: nessun blocco da due
