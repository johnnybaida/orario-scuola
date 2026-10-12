import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from conftest import aggiungi_lezioni, aggiungi_sostegno, problema_base
from solver import risolvi


def _vincolo(tipo, severita='rigido', peso=None, ambito=None, **parametri):
    return {'id': 1, 'tipo': tipo, 'ambito': ambito or {'livello': 'globale', 'ids': []},
            'parametri': parametri, 'severita': severita, 'peso': peso, 'attivo': True}


def _problema(ore_sostegno=2):
    p = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(p, 'ITA', 2)
    aggiungi_lezioni(p, 'MAT', 2)
    p['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': 2, 'ore': ore_sostegno}])
    return p


def _discipline_sostegno(p, r):
    """Disciplina della lezione della classe in ciascuna ora di sostegno."""
    disc = {l['id']: l['disciplina'] for l in p['lezioni']}
    per_slot = {a['slot']: disc[a['lezione']] for a in r['assegnazioni']}
    return [per_slot[c['slot']] for c in r['compresenze_sostegno']]


def test_s1_rigido_il_sostegno_sta_solo_nelle_discipline_scelte():
    p = _problema()
    p['vincoli'] = [_vincolo('S1_DISCIPLINE_PREFERITE', discipline=['MAT'])]
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    assert _discipline_sostegno(p, r) == ['MAT', 'MAT']


def test_s1_rigido_infattibile_se_le_ore_non_stanno():
    p = _problema(ore_sostegno=3)   # solo 2 ore di Matematica
    p['vincoli'] = [_vincolo('S1_DISCIPLINE_PREFERITE', discipline=['MAT'])]
    assert risolvi(p)['stato'] == 'infattibile'


def test_s2_rigido_il_sostegno_evita_le_discipline_escluse():
    p = _problema()
    p['vincoli'] = [_vincolo('S2_DISCIPLINE_ESCLUSE', discipline=['ITA'])]
    r = risolvi(p)
    assert _discipline_sostegno(p, r) == ['MAT', 'MAT']


def test_s2_rigido_infattibile_se_non_resta_posto():
    p = _problema(ore_sostegno=3)
    p['vincoli'] = [_vincolo('S2_DISCIPLINE_ESCLUSE', discipline=['ITA'])]
    assert risolvi(p)['stato'] == 'infattibile'


def test_s1_preferenziale_accetta_ma_minimizza_le_ore_fuori():
    p = _problema(ore_sostegno=3)
    p['vincoli'] = [_vincolo('S1_DISCIPLINE_PREFERITE', severita='preferenziale', peso=10, discipline=['MAT'])]
    r = risolvi(p)
    assert r['stato'] in ('ottimo', 'fattibile')
    assert sorted(_discipline_sostegno(p, r)) == ['ITA', 'MAT', 'MAT']
    assert r['violazioni_soft'][0]['conteggio'] == 10


def test_s2_ambito_docente_e_classe_non_toccano_gli_altri():
    p = _problema()
    p['vincoli'] = [_vincolo('S2_DISCIPLINE_ESCLUSE', ambito={'livello': 'docente', 'ids': [1]}, discipline=['ITA', 'MAT'])]
    assert risolvi(p)['stato'] in ('ottimo', 'fattibile')   # il docente 1 non fa sostegno: nessun effetto
    p['vincoli'] = [_vincolo('S2_DISCIPLINE_ESCLUSE', ambito={'livello': 'classe', 'ids': [1]}, discipline=['ITA', 'MAT'])]
    assert risolvi(p)['stato'] == 'infattibile'
