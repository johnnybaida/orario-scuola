import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _v(tipo, parametri, livello='classe', ids=(1,), severita='rigido', peso=None):
    return {'id': 1, 'tipo': tipo, 'ambito': {'livello': livello, 'ids': list(ids)}, 'parametri': parametri,
            'severita': severita, 'peso': peso, 'attivo': True}


def _ordini(risultato, problema, disciplina):
    slot = {s['id']: s['ordine'] for s in problema['slots']}
    ass = {a['lezione']: a['slot'] for a in risultato['assegnazioni']}
    return sorted(slot[ass[l['id']]] for l in problema['lezioni'] if l['disciplina'] == disciplina)


def _scuola(ore):
    """Una classe, un giorno da 4 slot, le discipline di `ore` ({codice: ore}) con un docente ciascuna."""
    p = problema_base(n_slot_giorno1=4)
    for i, (codice, n) in enumerate(ore.items(), start=1):
        if i > 1:
            p['docenti'].append({'id': i, 'indisponibili': []})
        aggiungi_lezioni(p, codice, n, docente_id=i)
    return p


def test_d3_vale_per_ciascuna_disciplina_dell_elenco():
    p = _scuola({'ITA': 2, 'MAT': 1, 'ART': 1})
    p['vincoli'] = [_v('D3_MAX_ORE_GIORNO', {'discipline': ['MAT'], 'max': 1})]
    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')   # MAT ha 1 ora: il limite non pesa

    p['vincoli'] = [_v('D3_MAX_ORE_GIORNO', {'discipline': ['MAT', 'ITA'], 'max': 1})]
    assert risolvi(p)['stato'] == 'infattibile'              # ITA ha 2 ore in un solo giorno: la stessa regola vale anche per lei


def test_d1_vale_per_ciascuna_disciplina_dell_elenco():
    p = _scuola({'ITA': 2, 'MAT': 2})
    p['vincoli'] = [_v('D1_BLOCCO_MIN_CONSECUTIVO', {'discipline': ['ITA', 'MAT'], 'min_consecutive': 2, 'n_blocchi_min': 1})]
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    for codice in ('ITA', 'MAT'):
        o = _ordini(r, p, codice)
        assert o[1] - o[0] == 1                              # tutte e due hanno la coppia

    p = _scuola({'ITA': 2, 'MAT': 1, 'ART': 1})
    p['vincoli'] = [_v('D1_BLOCCO_MIN_CONSECUTIVO', {'discipline': ['ITA', 'MAT'], 'min_consecutive': 2, 'n_blocchi_min': 1})]
    assert risolvi(p)['stato'] == 'infattibile'              # MAT ha una sola ora: non può fare un blocco


def test_d6_vietata_vale_per_tutte_le_discipline_dell_elenco():
    p = _scuola({'ITA': 1, 'MAT': 1, 'ART': 2})
    primo = p['slots'][0]['id']
    p['vincoli'] = [_v('D6_FASCIA_ORARIA', {'discipline': ['ITA', 'MAT'], 'tipo': 'vietata', 'slot_ids': [primo]})]
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    assigned = {a['lezione']: a['slot'] for a in r['assegnazioni']}
    assert all(assigned[l['id']] != primo for l in p['lezioni'] if l['disciplina'] in ('ITA', 'MAT'))


def test_d12_vale_per_ciascuna_disciplina_dell_elenco():
    p = _scuola({'ITA': 2, 'MAT': 2})
    p['vincoli'] = [_v('D12_BLOCCO_MAX_CONSECUTIVO', {'discipline': ['ITA', 'MAT'], 'max_consecutive': 1})]
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    for codice in ('ITA', 'MAT'):
        o = _ordini(r, p, codice)
        assert o[1] - o[0] > 1


def test_il_vecchio_parametro_disciplina_singola_funziona_ancora():
    p = _scuola({'ITA': 2, 'ART': 2})
    p['vincoli'] = [_v('D3_MAX_ORE_GIORNO', {'disciplina': 'ITA', 'max': 1})]
    assert risolvi(p)['stato'] == 'infattibile'


def test_d1_docente_senza_discipline_conta_tutte_le_lezioni():
    p = _scuola({'ITA': 1, 'MAT': 1, 'ART': 2})
    # lo stesso docente ha ITA e MAT: nessun elenco = tutte le sue lezioni (2 ore di fila possibili)
    p['lezioni'][1]['docenti'] = [1]
    p['vincoli'] = [_v('D1_BLOCCO_MIN_CONSECUTIVO', {'discipline': [], 'min_consecutive': 2, 'n_blocchi_min': 1}, livello='docente', ids=(1,))]
    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')
