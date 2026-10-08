from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _vincolo_c5(severita='preferenziale', peso=10, soglia=None, ambito=None):
    return {
        'id': 1,
        'tipo': 'C5_SPOSTAMENTI_PIANO',
        'ambito': ambito or {'livello': 'globale', 'ids': []},
        'parametri': {} if soglia is None else {'soglia': soglia},
        'severita': severita,
        'peso': peso if severita == 'preferenziale' else None,
        'attivo': True,
    }


def _scuola_dada():
    """Una classe, 3 ore in un giorno: due lezioni al piano 1 (aule A e C) e una al piano 3 (aula B)."""
    problema = problema_base(n_slot_giorno1=3)
    problema['aule'] = [
        {'id': 1, 'tipo': 'dada_a', 'capacita': 1, 'piano': 1},
        {'id': 2, 'tipo': 'dada_b', 'capacita': 1, 'piano': 3},
        {'id': 3, 'tipo': 'dada_c', 'capacita': 1, 'piano': 1},
    ]
    problema['docenti'] += [{'id': 2, 'indisponibili': []}, {'id': 3, 'indisponibili': []}]
    aggiungi_lezioni(problema, 'A', 1, docente_id=1, tipo_aula='dada_a')
    aggiungi_lezioni(problema, 'B', 1, docente_id=2, tipo_aula='dada_b')
    aggiungi_lezioni(problema, 'C', 1, docente_id=3, tipo_aula='dada_c')
    return problema


def _piani_per_slot(problema, risultato):
    piano_aula = {a['id']: a['piano'] for a in problema['aule']}
    return {a['slot']: piano_aula[a['aula']] for a in risultato['assegnazioni']}


def test_c5_preferisce_non_andare_dal_piano_1_al_3_e_tornare_al_1():
    problema = _scuola_dada()
    problema['vincoli'] = [_vincolo_c5()]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    piani = _piani_per_slot(problema, risultato)
    # 1-3-1 costerebbe 4 piani; l'ottimo tiene il piano 3 a un'estremità (1-1-3 o 3-1-1: costo 2)
    assert piani[2] == 1
    assert risultato['punteggio'] == 10 * 2


def test_c5_senza_il_vincolo_il_costo_non_conta():
    problema = _scuola_dada()

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] == 0


def test_c5_rigido_fattibile_con_soglia_che_copre_il_salto():
    problema = _scuola_dada()
    problema['vincoli'] = [_vincolo_c5(severita='rigido', soglia=2)]

    assert risolvi(problema)['stato'] in ('ottimo', 'fattibile')


def test_c5_rigido_infattibile_se_il_salto_e_obbligato_e_oltre_la_soglia():
    problema = _scuola_dada()
    problema['vincoli'] = [_vincolo_c5(severita='rigido', soglia=1)]   # il 3 è sempre adiacente a un 1: salto di 2

    assert risolvi(problema)['stato'] == 'infattibile'


def test_c5_senza_piano_nessun_effetto():
    problema = _scuola_dada()
    for aula in problema['aule']:
        aula['piano'] = None
    problema['vincoli'] = [_vincolo_c5(severita='rigido', soglia=0)]

    assert risolvi(problema)['stato'] in ('ottimo', 'fattibile')


def test_c5_usa_il_piano_della_classe_se_la_lezione_non_ha_aula():
    problema = problema_base(n_slot_giorno1=2)
    problema['aule'] = [{'id': 1, 'tipo': 'lab', 'capacita': 1, 'piano': 3}]
    problema['classi'][0]['piano'] = 0
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'LAB', 1, docente_id=1, tipo_aula='lab')
    aggiungi_lezioni(problema, 'ITA', 1, docente_id=2)   # senza aula: conta il piano della classe (0)
    problema['vincoli'] = [_vincolo_c5(severita='rigido', soglia=2)]

    assert risolvi(problema)['stato'] == 'infattibile'   # 0 -> 3 oltre la soglia
