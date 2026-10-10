from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo(id, tipo, parametri, livello='globale', ids=()):
    return {'id': id, 'tipo': tipo, 'ambito': {'livello': livello, 'ids': list(ids)}, 'parametri': parametri, 'severita': 'rigido', 'peso': None, 'attivo': True}


def test_l_infattibilita_indica_i_due_vincoli_in_conflitto_e_non_quello_innocuo():
    p = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(p, 'ITA', 2)
    aggiungi_lezioni(p, 'MAT', 2, docente_id=1)
    p['vincoli'] = [
        _vincolo(1, 'D3_MAX_ORE_GIORNO', {'disciplina': 'MAT', 'max': 4}),                 # innocuo
        _vincolo(2, 'D1_BLOCCO_MIN_CONSECUTIVO', {'disciplina': 'ITA', 'min_consecutive': 2, 'n_blocchi_min': 1}),
        _vincolo(3, 'D12_BLOCCO_MAX_CONSECUTIVO', {'disciplina': 'ITA', 'max_consecutive': 1}),   # contraddice il 2
    ]
    p['diagnosi_s'] = 60

    r = risolvi(p)

    assert r['stato'] == 'infattibile'
    righe = [x for x in r['diagnostica'] if x.startswith('Conflitto tra vincoli')]
    assert righe == ['Conflitto tra vincoli: 2, 3']


def test_il_conflitto_si_restringe_ai_docenti_che_lo_causano():
    p = problema_base(n_slot_giorno1=3)
    p['docenti'] += [{'id': 2, 'indisponibili': []}, {'id': 3, 'indisponibili': []}]
    aggiungi_lezioni(p, 'ITA', 3, docente_id=2)   # 3 ore tutte nell'unico giorno
    p['vincoli'] = [_vincolo(7, 'T4_ORE_GIORNO', {'max_ore': 1}, livello='docente', ids=[1, 2, 3])]
    p['diagnosi_s'] = 60

    r = risolvi(p)

    assert r['stato'] == 'infattibile'
    assert 'Conflitto tra vincoli: 7' in r['diagnostica']
    assert 'Vincolo 7 ristretto a docenti: 2' in r['diagnostica']


def test_se_i_vincoli_non_c_entrano_il_problema_e_nei_dati():
    p = problema_base(n_slot_giorno1=2)
    aggiungi_lezioni(p, 'ITA', 3)   # più ore degli slot attivi: impossibile con o senza vincoli
    p['vincoli'] = [_vincolo(1, 'D3_MAX_ORE_GIORNO', {'disciplina': 'ITA', 'max': 2})]
    p['diagnosi_s'] = 60

    r = risolvi(p)

    assert r['stato'] == 'infattibile'
    assert any('Anche senza i vincoli configurati' in x for x in r['diagnostica'])


def test_senza_budget_l_analisi_non_parte():
    p = problema_base(n_slot_giorno1=2)
    aggiungi_lezioni(p, 'ITA', 3)   # più ore degli slot: infattibile
    p['vincoli'] = [_vincolo(1, 'D3_MAX_ORE_GIORNO', {'disciplina': 'ITA', 'max': 2})]

    assert risolvi(p)['diagnostica'] == ['Nessuna soluzione soddisfa i vincoli rigidi con i dati forniti.']
