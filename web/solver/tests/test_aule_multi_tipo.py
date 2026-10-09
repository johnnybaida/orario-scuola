from conftest import aggiungi_lezioni, problema_base
from solver import risolvi


def _scuola(n_slot=1):
    """Tre classi, tre docenti: Italiano, Inglese e Spagnolo ciascuno con la propria aula DADA e tutti con un'aula condivisa."""
    problema = problema_base(n_slot_giorno1=n_slot)
    problema['classi'] += [{'id': 2, 'slots_attivi': problema['classi'][0]['slots_attivi']}, {'id': 3, 'slots_attivi': problema['classi'][0]['slots_attivi']}]
    problema['docenti'] += [{'id': 2, 'indisponibili': []}, {'id': 3, 'indisponibili': []}]
    problema['aule'] = [
        {'id': 1, 'tipo': 'dada_ita', 'capacita': 1}, {'id': 2, 'tipo': 'dada_ing', 'capacita': 1},
        {'id': 3, 'tipo': 'dada_spa', 'capacita': 1}, {'id': 4, 'tipo': 'dada_cond', 'capacita': 1},
    ]
    return problema


def _lezioni(problema, tipi_ita, tipi_ing, tipi_spa):
    for classe, docente, disciplina, tipi in [(1, 1, 'ITA', tipi_ita), (2, 2, 'ING', tipi_ing), (3, 3, 'SPA', tipi_spa)]:
        aggiungi_lezioni(problema, disciplina, 1, classe_id=classe, docente_id=docente, tipo_aula=tipi[0], bloccata_slot=1)
        problema['lezioni'][-1]['tipi_aula'] = tipi


def test_ogni_disciplina_usa_la_propria_aula_o_quella_condivisa():
    problema = _scuola()
    _lezioni(problema, ['dada_ita', 'dada_cond'], ['dada_ing', 'dada_cond'], ['dada_spa', 'dada_cond'])   # tutte e tre nello stesso slot

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    aule = [a['aula'] for a in risultato['assegnazioni']]
    assert len(set(aule)) == 3                                  # tre aule diverse: nessuna capienza superata
    assert {a['lezione']: a['aula'] for a in risultato['assegnazioni']}[1] in (1, 4)   # Italiano: la sua o la condivisa


def test_infattibile_se_piu_discipline_hanno_solo_la_condivisa_e_la_usano_insieme():
    problema = _scuola()
    _lezioni(problema, ['dada_cond'], ['dada_cond'], ['dada_spa', 'dada_cond'])

    assert risolvi(problema)['stato'] == 'infattibile'   # Italiano e Inglese nello stesso slot, una sola aula condivisa


def test_la_condivisa_basta_a_chi_ha_perso_la_propria():
    problema = _scuola()
    problema['aule'] = [a for a in problema['aule'] if a['id'] != 1]   # Italiano non ha più la sua aula
    _lezioni(problema, ['dada_ita', 'dada_cond'], ['dada_ing', 'dada_cond'], ['dada_spa', 'dada_cond'])

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert {a['lezione']: a['aula'] for a in risultato['assegnazioni']}[1] == 4   # Italiano ripiega sulla condivisa

