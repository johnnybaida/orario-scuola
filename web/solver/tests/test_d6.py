from conftest import aggiungi_lezioni, completa_slot_rimanenti, problema_base
from solver import risolvi


def _vincolo_d6(tipo, slot_ids, severita='rigido', peso=None):
    return {
        'id': 1,
        'tipo': 'D6_FASCIA_ORARIA',
        'ambito': {'livello': 'classe', 'ids': [1]},
        'parametri': {'disciplina': 'IRC', 'tipo': tipo, 'slot_ids': slot_ids},
        'severita': severita,
        'peso': peso,
        'attivo': True,
    }


def test_d6_vietata_fattibile_se_restano_slot_liberi():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'IRC', 1)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d6('vietata', [1])]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    slot_irc = next(a['slot'] for a in risultato['assegnazioni'] if a['lezione'] == 1)
    assert slot_irc != 1


def test_d6_vietata_infattibile_se_copre_tutti_gli_slot():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'IRC', 1)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d6('vietata', [1, 2, 3, 4])]

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_d6_preferita_forza_lo_slot_se_rigido():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'IRC', 1)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo_d6('preferita', [4])]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    slot_irc = next(a['slot'] for a in risultato['assegnazioni'] if a['lezione'] == 1)
    assert slot_irc == 4
