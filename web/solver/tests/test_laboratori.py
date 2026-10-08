from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def test_il_docente_in_laboratorio_non_ha_lezioni_in_quello_slot():
    problema = problema_base(n_slot_giorno1=3)
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 1, docente_id=1)
    aggiungi_lezioni(problema, 'RIEMPI', 2, docente_id=2)
    problema['occupazioni_fisse'] = [{'docente': 1, 'slot': 1, 'aula': None, 'laboratorio': 1}]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    ita = next(a for a in risultato['assegnazioni'] if a['lezione'] == 1)
    assert ita['slot'] != 1


def test_infattibile_se_il_laboratorio_toglie_al_docente_lultimo_slot_possibile():
    problema = problema_base(n_slot_giorno1=1)
    aggiungi_lezioni(problema, 'ITA', 1, docente_id=1)
    problema['occupazioni_fisse'] = [{'docente': 1, 'slot': 1, 'aula': None, 'laboratorio': 1}]

    assert risolvi(problema)['stato'] == 'infattibile'


def test_laula_occupata_dal_laboratorio_non_ospita_lezioni_in_quello_slot():
    problema = problema_base(n_slot_giorno1=1)
    problema['aule'] = [{'id': 1, 'tipo': 'laboratorio', 'capacita': 1}]
    aggiungi_lezioni(problema, 'SCI', 1, docente_id=1, tipo_aula='laboratorio')
    problema['occupazioni_fisse'] = [{'docente': 2, 'slot': 1, 'aula': 1, 'laboratorio': 1}]   # un altro docente, stessa aula

    assert risolvi(problema)['stato'] == 'infattibile'


def test_laula_con_capienza_due_resta_utilizzabile_con_un_laboratorio():
    problema = problema_base(n_slot_giorno1=1)
    problema['aule'] = [{'id': 1, 'tipo': 'palestra', 'capacita': 2}]
    aggiungi_lezioni(problema, 'MOT', 1, docente_id=1, tipo_aula='palestra')
    problema['occupazioni_fisse'] = [{'docente': 2, 'slot': 1, 'aula': 1, 'laboratorio': 1}]

    assert risolvi(problema)['stato'] in ('ottimo', 'fattibile')
