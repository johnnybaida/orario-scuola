import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from conftest import aggiungi_lezioni, aggiungi_sostegno, problema_base
from solver import risolvi


def _vincolo(severita='rigido', peso=None, **parametri):
    return {'id': 1, 'tipo': 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito': {'livello': 'globale', 'ids': []},
            'parametri': parametri, 'severita': severita, 'peso': peso, 'attivo': True}


def _tre_docenti(n_slot=6, ore=2):
    p = problema_base(n_slot_giorno1=n_slot)
    aggiungi_lezioni(p, 'ITA', n_slot)
    p['docenti'] += [{'id': i, 'indisponibili': []} for i in (2, 3, 4)]
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': i, 'ore': ore} for i in (2, 3, 4)])
    return p


def _insieme(r):
    return Counter(c['slot'] for c in r['compresenze_sostegno'])


def test_s5_rigido_mai_due_docenti_di_sostegno_insieme_se_ci_stanno():
    p = _tre_docenti(n_slot=6, ore=2)   # 6 ore di sostegno in 6 slot: una per slot
    p['vincoli'] = [_vincolo(max_insieme=1)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert max(_insieme(r).values()) == 1


def test_s5_rigido_infattibile_se_le_ore_non_stanno_senza_sovrapporsi():
    p = _tre_docenti(n_slot=4, ore=2)   # 6 ore di sostegno in 4 slot: qualcuno si sovrappone
    p['vincoli'] = [_vincolo(max_insieme=1)]

    assert risolvi(p)['stato'] == 'infattibile'


def test_s5_preferenziale_accetta_la_sovrapposizione_ma_la_minimizza():
    p = _tre_docenti(n_slot=4, ore=2)
    p['vincoli'] = [_vincolo(severita='preferenziale', peso=10, max_insieme=1)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert sum(v - 1 for v in _insieme(r).values()) == 2   # 6 ore in 4 slot: il minimo sono 2 sovrapposizioni
    assert r['violazioni_soft'][0]['conteggio'] == 20   # 2 sovrapposizioni x peso 10


def test_s5_distribuzione_nella_settimana_rispetta_il_tetto_giornaliero():
    p = problema_base(n_slot_giorno1=3)
    p['slots'] = [{'id': 10 * g + o, 'giorno': g, 'ordine': o, 'intervallo_dopo': False} for g in (1, 2) for o in (1, 2, 3)]
    p['classi'][0]['slots_attivi'] = [s['id'] for s in p['slots']]
    aggiungi_lezioni(p, 'ITA', 6)
    p['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': 2, 'ore': 4}])
    p['vincoli'] = [_vincolo(tolleranza_giorno=0)]   # 4 ore su 2 giorni: al massimo 2 al giorno

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    per_giorno = Counter(c['slot'] // 10 for c in r['compresenze_sostegno'])
    assert per_giorno == {1: 2, 2: 2}


def test_s5_i_giorni_di_indisponibilita_non_contano_nel_tetto():
    p = problema_base(n_slot_giorno1=3)
    p['slots'] = [{'id': 10 * g + o, 'giorno': g, 'ordine': o, 'intervallo_dopo': False} for g in (1, 2) for o in (1, 2, 3)]
    p['classi'][0]['slots_attivi'] = [s['id'] for s in p['slots']]
    aggiungi_lezioni(p, 'ITA', 6)
    p['docenti'].append({'id': 2, 'indisponibili': [21, 22, 23]})   # il secondo giorno non c'è mai
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': 2, 'ore': 3}])
    p['vincoli'] = [_vincolo(tolleranza_giorno=0)]   # un solo giorno utile: 3 ore tutte lì, senza essere infattibile

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert len(r['compresenze_sostegno']) == 3
