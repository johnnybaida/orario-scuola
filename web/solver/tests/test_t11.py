from conftest import aggiungi_lezioni, aggiungi_sostegno, problema_base
from solver import risolvi


def _scuola(n_slot=4, ore=2, indisponibili=None):
    """Il docente 1 ha `ore` ore di ITA; le altre ore della classe le fa il docente 3."""
    p = problema_base(n_slot_giorno1=n_slot, docente_indisponibili=indisponibili)
    p['docenti'].append({'id': 3, 'indisponibili': []})
    aggiungi_lezioni(p, 'ITA', ore)
    aggiungi_lezioni(p, 'MAT', n_slot - ore, docente_id=3)
    return p


def _vincolo(docente=1, slot=(1,), min_ore=1, severita='rigido', peso=None):
    return {'id': 1, 'tipo': 'T11_ORE_IN_FASCIA', 'ambito': {'livello': 'docente', 'ids': [docente]},
            'parametri': {'min_ore': min_ore, 'slot_ids': list(slot)}, 'severita': severita, 'peso': peso, 'attivo': True}


def _ore_nello_slot(r, p, slot, docente=1):
    lez = {l['id']: l for l in p['lezioni']}
    return sum(1 for a in r['assegnazioni'] if a['slot'] in slot and docente in lez[a['lezione']]['docenti'])


def test_t11_rigido_porta_le_ore_del_docente_negli_slot_scelti():
    p = _scuola()
    p['vincoli'] = [_vincolo(slot=(3, 4), min_ore=2)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert _ore_nello_slot(r, p, {3, 4}) == 2   # le sole due ore del docente stanno negli slot 3 e 4


def test_t11_rigido_infattibile_se_il_docente_non_e_disponibile_in_nessuno_slot_della_fascia():
    p = _scuola(indisponibili=[1, 2])
    p['vincoli'] = [_vincolo(slot=(1, 2))]

    assert risolvi(p)['stato'] == 'infattibile'


def test_t11_preferenziale_costa_peso_per_ogni_ora_mancante():
    p = _scuola(indisponibili=[1, 2])
    p['vincoli'] = [_vincolo(slot=(1, 2), min_ore=2, severita='preferenziale', peso=10)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert r['violazioni_soft'][0]['conteggio'] == 20   # 2 ore mancanti x peso 10


def test_t11_conta_anche_le_ore_di_sostegno():
    p = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(p, 'ITA', 4)
    p['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': 2, 'ore': 1}])
    p['vincoli'] = [_vincolo(docente=2, slot=(4,), min_ore=1)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert [c['slot'] for c in r['compresenze_sostegno']] == [4]
