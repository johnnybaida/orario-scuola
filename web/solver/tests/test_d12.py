import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from conftest import aggiungi_lezioni, completa_slot_rimanenti, problema_base
from solver import risolvi


def _vincolo(massimo=1, livello='classe', ids=(1,), disciplina='ITA', severita='rigido', peso=None):
    parametri = {'max_consecutive': massimo}
    if disciplina:
        parametri['disciplina'] = disciplina
    return {'id': 1, 'tipo': 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito': {'livello': livello, 'ids': list(ids)},
            'parametri': parametri, 'severita': severita, 'peso': peso, 'attivo': True}


def _consecutive(risultato, problema, disciplina):
    """Ordini degli slot (giorno 1) occupati dalla disciplina, ordinati."""
    slot = {s['id']: s['ordine'] for s in problema['slots']}
    assegnato = {a['lezione']: a['slot'] for a in risultato['assegnazioni']}
    return sorted(slot[assegnato[l['id']]] for l in problema['lezioni'] if l['disciplina'] == disciplina)


def test_d12_fattibile_le_ore_non_sono_adiacenti():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 2)
    completa_slot_rimanenti(problema)
    problema['vincoli'] = [_vincolo(massimo=1)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    ordini = _consecutive(risultato, problema, 'ITA')
    assert ordini[1] - ordini[0] > 1   # mai due ore di fila


def test_d12_infattibile_se_tutte_le_ore_devono_stare_di_fila():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 4)   # 4 slot, 4 ore: un blocco da 4
    problema['vincoli'] = [_vincolo(massimo=2)]

    assert risolvi(problema)['stato'] == 'infattibile'


def test_d12_preferenziale_non_blocca_ma_penalizza():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 4)
    problema['vincoli'] = [_vincolo(massimo=2, severita='preferenziale', peso=10)]

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert risultato['punteggio'] > 0
    assert risultato['violazioni_soft'] and risultato['violazioni_soft'][0]['vincolo_id'] == 1


def test_d12_con_il_massimo_alto_non_cambia_nulla():
    problema = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(problema, 'ITA', 4)
    problema['vincoli'] = [_vincolo(massimo=4)]

    assert risolvi(problema)['stato'] in ('ottimo', 'fattibile')


def test_d12_ambito_docente_senza_disciplina_conta_tutte_le_lezioni_del_docente():
    problema = problema_base(n_slot_giorno1=3)
    aggiungi_lezioni(problema, 'ITA', 2)
    aggiungi_lezioni(problema, 'MAT', 1)    # lo stesso docente fa tutte e 3 le ore: 3 di fila
    problema['vincoli'] = [_vincolo(massimo=2, livello='docente', ids=(1,), disciplina=None)]

    assert risolvi(problema)['stato'] == 'infattibile'


def test_d12_ambito_globale_vale_per_tutte_le_classi():
    problema = problema_base(n_slot_giorno1=4)
    problema['classi'].append({'id': 2, 'slots_attivi': problema['classi'][0]['slots_attivi']})
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=1, classe_id=1)
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=2, classe_id=2)
    problema['vincoli'] = [_vincolo(massimo=3, livello='globale', ids=())]

    assert risolvi(problema)['stato'] == 'infattibile'   # nessuna classe può avere 4 ore di fila
