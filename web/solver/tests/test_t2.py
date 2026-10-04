from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo_t2(n_giorni, severita='rigido', peso=None, preferenze=None):
    return {
        'id': 1,
        'tipo': 'T2_GIORNO_LIBERO',
        'ambito': {'livello': 'docente', 'ids': [1]},
        'parametri': {'n_giorni': n_giorni, 'preferenze': preferenze or []},
        'severita': severita,
        'peso': peso,
        'attivo': True,
    }


def _problema_due_giorni():
    return problema_base(n_slot_giorno1=2, extra_slots={2: 2})


def test_t2_fattibile_con_un_secondo_docente_che_libera_un_giorno():
    problema = _problema_due_giorni()
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 2, docente_id=1)  # giorno 1
    aggiungi_lezioni(problema, 'MAT', 2, docente_id=2)  # giorno 2
    problema['vincoli'] = [_vincolo_t2(n_giorni=1)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')


def test_t2_infattibile_se_un_solo_docente_copre_tutta_la_settimana():
    problema = _problema_due_giorni()
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=1)
    problema['vincoli'] = [_vincolo_t2(n_giorni=1)]

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_t2_preferenziale_penalizza_la_mancanza_di_giorno_libero():
    problema = _problema_due_giorni()
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=1)
    problema['vincoli'] = [_vincolo_t2(n_giorni=1, severita='preferenziale', peso=7)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] == 7
