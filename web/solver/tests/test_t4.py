from collections import Counter

from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo(min_ore=None, max_ore=None, severita='rigido', peso=None, docente=1):
    parametri = {k: v for k, v in (('min_ore', min_ore), ('max_ore', max_ore)) if v}
    ambito = {'livello': 'docente', 'ids': [docente]} if docente else {'livello': 'globale', 'ids': []}
    return {'id': 1, 'tipo': 'T4_ORE_GIORNO', 'ambito': ambito, 'parametri': parametri, 'severita': severita, 'peso': peso, 'attivo': True}


def _scuola(ore_ita=4, indisponibili=None):
    """Due giorni da 3 ore (slot 1-3 e 4-6): il docente 1 ha `ore_ita` ore di ITA, il docente 3 le altre."""
    p = problema_base(n_slot_giorno1=3, extra_slots={2: 3}, docente_indisponibili=indisponibili)
    p['docenti'].append({'id': 3, 'indisponibili': []})
    aggiungi_lezioni(p, 'ITA', ore_ita)
    aggiungi_lezioni(p, 'MAT', 6 - ore_ita, docente_id=3)
    return p


def _per_giorno(r, p):
    giorno = {s['id']: s['giorno'] for s in p['slots']}
    lez = {l['id']: l for l in p['lezioni']}
    return Counter(giorno[a['slot']] for a in r['assegnazioni'] if 1 in lez[a['lezione']]['docenti'])


def test_t4_minimo_porta_il_docente_in_ogni_giorno():
    p = _scuola(ore_ita=2)
    p['vincoli'] = [_vincolo(min_ore=1)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert _per_giorno(r, p) == {1: 1, 2: 1}   # le sole due ore del docente: una al giorno


def test_t4_massimo_limita_le_ore_al_giorno():
    p = _scuola(ore_ita=4)
    p['vincoli'] = [_vincolo(max_ore=2)]

    r = risolvi(p)

    assert _per_giorno(r, p) == {1: 2, 2: 2}
    p['vincoli'] = [_vincolo(max_ore=1)]
    assert risolvi(p)['stato'] == 'infattibile'   # 4 ore in 2 giorni a 1 ora al giorno non stanno


def test_t4_non_pretende_ore_nei_giorni_di_indisponibilita():
    p = _scuola(ore_ita=2, indisponibili=[4, 5, 6])   # il secondo giorno il docente non c'è
    p['vincoli'] = [_vincolo(min_ore=2)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert _per_giorno(r, p) == {1: 2}


def test_t4_minimo_rigido_si_riduce_se_le_ore_sono_meno_dei_giorni():
    p = _scuola(ore_ita=1)   # 1 ora in 2 giorni: «almeno 1 al giorno» non è possibile, non rende infattibile
    p['vincoli'] = [_vincolo(min_ore=1)]

    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')


def test_t4_infattibile_se_minimo_e_massimo_si_contraddicono():
    p = _scuola(ore_ita=4)
    p['vincoli'] = [_vincolo(min_ore=2, max_ore=1)]

    assert risolvi(p)['stato'] == 'infattibile'


def test_t4_preferenziale_costa_peso_per_ogni_ora_sotto_il_minimo():
    p = _scuola(ore_ita=2)   # 2 ore in 2 giorni con minimo 2: mancano in totale 2 ore, comunque siano distribuite
    p['vincoli'] = [_vincolo(min_ore=2, severita='preferenziale', peso=10)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert r['violazioni_soft'][0]['conteggio'] == 20


def test_t4_globale_vale_per_tutti_i_docenti():
    p = _scuola(ore_ita=2)   # docente 3: 4 ore di MAT in 2 giorni
    p['vincoli'] = [_vincolo(max_ore=2, docente=None)]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    giorno = {s['id']: s['giorno'] for s in p['slots']}
    lez = {l['id']: l for l in p['lezioni']}
    mat = Counter(giorno[a['slot']] for a in r['assegnazioni'] if 3 in lez[a['lezione']]['docenti'])
    assert max(mat.values()) <= 2
