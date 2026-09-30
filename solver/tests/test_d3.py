from conftest import aggiungi_lezioni, completa_slot_rimanenti, problema_base
from solver import risolvi


def _vincolo_d3(massimo, severita='rigido', peso=None):
    return {
        'id': 1,
        'tipo': 'D3_MAX_ORE_GIORNO',
        'ambito': {'livello': 'classe', 'ids': [1]},
        'parametri': {'disciplina': 'MAT', 'max': massimo},
        'severita': severita,
        'peso': peso,
        'attivo': True,
    }


def test_d3_fattibile_entro_il_massimo():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'MAT', 2)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d3(massimo=2)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')


def test_d3_infattibile_se_supera_il_massimo_e_non_ce_altro_giorno():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'MAT', 3)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d3(massimo=2)]

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_d3_preferenziale_penalizza_leccesso():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'MAT', 3)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d3(massimo=2, severita='preferenziale', peso=5)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] == 5
