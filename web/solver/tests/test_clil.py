from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _due_classi():
    """Due classi, due slot: la docente CLIL (id 3) non può essere in due classi nello stesso slot."""
    p = problema_base(n_slot_giorno1=2)
    p['classi'].append({'id': 2, 'slots_attivi': p['classi'][0]['slots_attivi']})
    p['docenti'] += [{'id': i, 'indisponibili': []} for i in (2, 3, 4, 5)]
    aggiungi_lezioni(p, 'GEO', 1, docente_id=1, classe_id=1)
    aggiungi_lezioni(p, 'SCI', 1, docente_id=2, classe_id=2)
    aggiungi_lezioni(p, 'ART', 1, docente_id=4, classe_id=1)   # ore di riempimento: ogni slot attivo va coperto
    aggiungi_lezioni(p, 'ART', 1, docente_id=5, classe_id=2)
    return p


def test_la_docente_clil_e_in_compresenza_su_due_classi_in_slot_diversi():
    p = _due_classi()
    p['lezioni'][0]['docenti'] = [1, 3]   # GEO classe 1 con CLIL
    p['lezioni'][1]['docenti'] = [2, 3]   # SCI classe 2 con CLIL

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    slot = {a['lezione']: a['slot'] for a in r['assegnazioni']}
    assert slot[1] != slot[2]   # la stessa persona non è in due classi insieme


def test_infattibile_se_la_docente_clil_deve_essere_in_due_classi_nello_stesso_slot():
    p = _due_classi()
    p['lezioni'][0]['docenti'] = [1, 3]
    p['lezioni'][1]['docenti'] = [2, 3]
    p['lezioni'][0]['bloccata_slot'] = 1
    p['lezioni'][1]['bloccata_slot'] = 1

    assert risolvi(p)['stato'] == 'infattibile'
