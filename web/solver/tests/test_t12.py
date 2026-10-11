from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _scuola(indisponibili=None):
    """Quattro ore: il docente 1 ne ha 2 (ITA), il docente 3 le altre 2. Il docente 1 sorveglia la mensa dopo la 2ª ora."""
    p = problema_base(n_slot_giorno1=4, docente_indisponibili=indisponibili)
    p['docenti'].append({'id': 3, 'indisponibili': []})
    aggiungi_lezioni(p, 'ITA', 2)
    aggiungi_lezioni(p, 'MAT', 2, docente_id=3)
    p['assistenze_mensa'] = [{'docente': 1, 'giorno': 1, 'ordine': 2}]
    return p


def _vincolo(modo, min_ore=1, ambito=None, severita='rigido', peso=None):
    return {'id': 1, 'tipo': 'T12_ORE_ATTORNO_MENSA', 'ambito': ambito or {'livello': 'globale', 'ids': []},
            'parametri': {'modo': modo, 'min_ore': min_ore}, 'severita': severita, 'peso': peso, 'attivo': True}


def _ore(r, p, slot, docente=1):
    lez = {l['id']: l for l in p['lezioni']}
    return sum(1 for a in r['assegnazioni'] if a['slot'] in slot and docente in lez[a['lezione']]['docenti'])


def test_t12_a_cavallo_da_un_ora_prima_e_una_dopo():
    p = _scuola()
    p['vincoli'] = [_vincolo('a_cavallo')]

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert _ore(r, p, {1, 2}) == 1 and _ore(r, p, {3, 4}) == 1


def test_t12_prima_porta_almeno_un_ora_prima_della_mensa():
    p = _scuola()
    p['vincoli'] = [_vincolo('prima')]

    assert _ore(risolvi(p), p, {1, 2}) >= 1


def test_t12_rigido_infattibile_se_il_docente_non_puo_stare_dopo():
    p = _scuola(indisponibili=[3, 4])
    p['vincoli'] = [_vincolo('dopo')]

    assert risolvi(p)['stato'] == 'infattibile'


def test_t12_prima_o_dopo_basta_una_delle_due_parti():
    p = _scuola(indisponibili=[3, 4])
    p['vincoli'] = [_vincolo('prima_o_dopo')]

    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')


def test_t12_preferenziale_costa_peso_per_ora_mancante():
    p = _scuola(indisponibili=[3, 4])
    p['vincoli'] = [_vincolo('dopo', severita='preferenziale', peso=10)]

    r = risolvi(p)

    assert r['violazioni_soft'][0]['conteggio'] == 10


def test_t12_ambito_docente_ignora_gli_altri_sorveglianti():
    p = _scuola(indisponibili=[3, 4])
    p['vincoli'] = [_vincolo('dopo', ambito={'livello': 'docente', 'ids': [3]})]

    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')
