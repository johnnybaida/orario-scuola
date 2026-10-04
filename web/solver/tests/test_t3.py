from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo_t3(max_per_giorno, severita='rigido', peso=None):
    return {
        'id': 1,
        'tipo': 'T3_MAX_ORE_BUCHE',
        'ambito': {'livello': 'docente', 'ids': [1]},
        'parametri': {'max_per_giorno': max_per_giorno},
        'severita': severita,
        'peso': peso,
        'attivo': True,
    }


def _problema_con_due_buche_forzate():
    problema = problema_base(n_slot_giorno1=4)
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 1, docente_id=1, bloccata_slot=1)
    aggiungi_lezioni(problema, 'ITA', 1, docente_id=1, bloccata_slot=4)
    aggiungi_lezioni(problema, 'MAT', 2, docente_id=2)
    return problema


def test_t3_fattibile_quando_il_massimo_copre_le_buche_reali():
    problema = _problema_con_due_buche_forzate()
    problema['vincoli'] = [_vincolo_t3(max_per_giorno=2)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')


def test_t3_infattibile_se_il_massimo_e_inferiore_alle_buche_forzate():
    problema = _problema_con_due_buche_forzate()
    problema['vincoli'] = [_vincolo_t3(max_per_giorno=1)]

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_t3_preferenziale_penalizza_leccesso_di_buche():
    problema = _problema_con_due_buche_forzate()
    problema['vincoli'] = [_vincolo_t3(max_per_giorno=0, severita='preferenziale', peso=3)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] == 6  # 2 buche * peso 3
