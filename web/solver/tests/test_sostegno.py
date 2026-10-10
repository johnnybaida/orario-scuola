from conftest import aggiungi_lezioni, aggiungi_sostegno, problema_base
from solver import risolvi


def test_sostegno_per_alunno_fattibile():
    problema = problema_base(n_slot_giorno1=2)
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 2, docente_id=1)
    aggiungi_sostegno(problema, 1, [{'codice': '1B-S1', 'ore': 2, 'docente_unico': False}], [{'id': 2, 'ore': 2}])

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    assert len(risultato['compresenze_sostegno']) == 2
    assert all(c['docente'] == 2 and c['codice'] == '1B-S1' for c in risultato['compresenze_sostegno'])


def test_sostegno_infattibile_se_il_docente_non_ha_slot_sufficienti():
    problema = problema_base(n_slot_giorno1=2, docente_indisponibili=[])
    problema['docenti'].append({'id': 2, 'indisponibili': [1, 2]})  # nessuno slot disponibile
    aggiungi_lezioni(problema, 'ITA', 2, docente_id=1)
    aggiungi_sostegno(problema, 1, [{'codice': '1B-S1', 'ore': 2, 'docente_unico': False}], [{'id': 2, 'ore': 2}])

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_sostegno_docente_unico_assegna_un_solo_docente_al_codice():
    # due fabbisogni e due docenti sostegno, ore perfettamente divise in due:
    # il docente_unico sul primo fabbisogno costringe l'altro docente a
    # coprire per intero il secondo fabbisogno.
    problema = problema_base(n_slot_giorno1=4)
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    problema['docenti'].append({'id': 3, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=1)
    aggiungi_sostegno(
        problema, 1,
        [
            {'codice': '1B-S1', 'ore': 2, 'docente_unico': True},
            {'codice': '1B-S2', 'ore': 2, 'docente_unico': False},
        ],
        [{'id': 2, 'ore': 2}, {'id': 3, 'ore': 2}],
    )

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    docenti_s1 = {c['docente'] for c in risultato['compresenze_sostegno'] if c['codice'] == '1B-S1'}
    assert len(docenti_s1) == 1


def test_sostegno_per_classe_copre_il_fabbisogno_massimo():
    problema = problema_base(n_slot_giorno1=4)
    problema['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_lezioni(problema, 'ITA', 4, docente_id=1)
    aggiungi_sostegno(
        problema, 1,
        [{'codice': '1B-S1', 'ore': 3, 'docente_unico': False}, {'codice': '1B-S2', 'ore': 2, 'docente_unico': False}],
        [{'id': 2, 'ore': 3}],
        conteggio='per_classe',
    )

    risultato = risolvi(problema)

    assert risultato['stato'] in ('ottimo', 'fattibile')
    slot_coperti = {c['slot'] for c in risultato['compresenze_sostegno']}
    assert len(slot_coperti) == 3
    codici_per_slot = {c['codice'] for c in risultato['compresenze_sostegno']}
    assert codici_per_slot == {'1B-S1', '1B-S2'}


def test_sostegno_rispetta_lesclusivita_del_docente_con_altra_classe():
    problema = problema_base(n_slot_giorno1=1)
    problema['classi'].append({'id': 2, 'slots_attivi': problema['classi'][0]['slots_attivi']})
    problema['docenti'].append({'id': 2, 'indisponibili': []})

    aggiungi_lezioni(problema, 'ITA', 1, classe_id=1, docente_id=1)
    aggiungi_lezioni(problema, 'MAT', 1, classe_id=2, docente_id=2)  # docente 2 impegnato in classe 2 nell'unico slot

    aggiungi_sostegno(problema, 1, [{'codice': '1B-S1', 'ore': 1, 'docente_unico': False}], [{'id': 2, 'ore': 1}])

    risultato = risolvi(problema)

    assert risultato['stato'] == 'infattibile'


def test_senza_fabbisogni_i_docenti_assegnati_sono_in_compresenza_per_le_loro_ore():
    p = problema_base(n_slot_giorno1=4)
    aggiungi_lezioni(p, 'ITA', 4)
    p['docenti'].append({'id': 2, 'indisponibili': []})
    aggiungi_sostegno(p, classe_id=1, fabbisogni=[], docenti=[{'id': 2, 'ore': 3}])

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert len(r['compresenze_sostegno']) == 3
    assert all(c['docente'] == 2 and c['codice'] is None for c in r['compresenze_sostegno'])
    assert len({c['slot'] for c in r['compresenze_sostegno']}) == 3


def _due_classi_con_lo_stesso_docente(n_slot, ore_per_classe):
    p = problema_base(n_slot_giorno1=n_slot)
    p['classi'].append({'id': 2, 'slots_attivi': p['classi'][0]['slots_attivi']})
    p['docenti'] += [{'id': 2, 'indisponibili': []}, {'id': 3, 'indisponibili': []}]
    aggiungi_lezioni(p, 'ITA', n_slot, docente_id=1, classe_id=1)
    aggiungi_lezioni(p, 'MAT', n_slot, docente_id=3, classe_id=2)
    for classe in (1, 2):
        aggiungi_sostegno(p, classe_id=classe, fabbisogni=[], docenti=[{'id': 2, 'ore': ore_per_classe}])
    return p


def test_lo_stesso_docente_di_sostegno_non_e_in_due_classi_nello_stesso_slot():
    p = _due_classi_con_lo_stesso_docente(n_slot=4, ore_per_classe=2)

    r = risolvi(p)

    assert r['stato'] in ('ottimo', 'fattibile')
    assert len(r['compresenze_sostegno']) == 4
    assert len({c['slot'] for c in r['compresenze_sostegno']}) == 4   # 4 slot diversi: mai due classi insieme


def test_infattibile_se_le_ore_del_docente_di_sostegno_in_due_classi_non_stanno_negli_slot():
    p = _due_classi_con_lo_stesso_docente(n_slot=3, ore_per_classe=2)   # 2 + 2 = 4 ore in soli 3 slot

    assert risolvi(p)['stato'] == 'infattibile'
