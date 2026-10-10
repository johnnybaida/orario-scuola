from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo(modo='segue', min_coppie=None, severita='rigido', peso=None, prima=('ITA',), dopo=('STO',)):
    parametri = {'discipline': list(prima), 'discipline_seguite': list(dopo), 'modo': modo}
    if min_coppie:
        parametri['min_coppie'] = min_coppie
    return {'id': 1, 'tipo': 'D13_DISCIPLINA_SEGUITA', 'ambito': {'livello': 'globale', 'ids': []},
            'parametri': parametri, 'severita': severita, 'peso': peso, 'attivo': True}


def _scuola(ita, sto, mat, **vincolo):
    p = problema_base(n_slot_giorno1=ita + sto + mat)
    aggiungi_lezioni(p, 'ITA', ita)
    aggiungi_lezioni(p, 'STO', sto)
    aggiungi_lezioni(p, 'MAT', mat)
    p['vincoli'] = [_vincolo(**vincolo)]
    return p


def _slot(r, p, disciplina):
    lez = {l['id']: l['disciplina'] for l in p['lezioni']}
    ordine = {s['id']: s['ordine'] for s in p['slots']}
    return sorted(ordine[a['slot']] for a in r['assegnazioni'] if lez[a['lezione']] == disciplina)


def test_d13_rigido_ogni_lezione_di_partenza_e_seguita_dalla_successiva():
    p = _scuola(1, 1, 2)

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert _slot(r, p, 'STO')[0] == _slot(r, p, 'ITA')[0] + 1


def test_d13_rigido_infattibile_se_le_ore_di_partenza_sono_piu_delle_successive():
    assert risolvi(_scuola(2, 1, 1))['stato'] == 'infattibile'


def test_d13_preferenziale_costa_peso_per_ogni_lezione_senza_la_successiva():
    r = risolvi(_scuola(2, 1, 1, severita='preferenziale', peso=10))

    assert r['stato'] in ('ottimo', 'fattibile')
    assert r['violazioni_soft'][0]['conteggio'] == 10   # una delle due ITA resta senza STO dopo


def test_d13_con_min_coppie_bastano_quelle_richieste():
    p = _scuola(2, 1, 1, min_coppie=1)

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert any(s + 1 in _slot(r, p, 'STO') for s in _slot(r, p, 'ITA'))
    assert risolvi(_scuola(2, 1, 1, min_coppie=2))['stato'] == 'infattibile'


def test_d13_non_segue_evita_la_coppia_ma_non_se_le_ore_sono_fissate():
    p = _scuola(1, 1, 2, modo='non_segue')
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    assert _slot(r, p, 'STO')[0] != _slot(r, p, 'ITA')[0] + 1

    p = problema_base(n_slot_giorno1=2)
    aggiungi_lezioni(p, 'ITA', 1, bloccata_slot=1)
    aggiungi_lezioni(p, 'STO', 1, bloccata_slot=2)
    p['vincoli'] = [_vincolo(modo='non_segue')]
    assert risolvi(p)['stato'] == 'infattibile'
