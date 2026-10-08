"""Costruisce le variabili CP-SAT condivise (occupazione lezioni/docenti/
discipline) a partire dal problema JSON, e i vincoli rigidi di sistema
H1-H10 (vedi CLAUDE.md). I moduli in constraints/*.py leggono questo
contesto per aggiungere i vincoli configurabili D1,D3,D6,T2,T3."""

from collections import defaultdict

from ortools.sat.python import cp_model

from constraints.util import reify_or


class Contesto:
    def __init__(self, model: cp_model.CpModel, problema: dict):
        self.model = model
        self.problema = problema

        self.slots = {s['id']: s for s in problema['slots']}
        self.slots_by_day = defaultdict(list)
        for s in sorted(self.slots.values(), key=lambda s: (s['giorno'], s['ordine'])):
            self.slots_by_day[s['giorno']].append(s['id'])
        self.giorni = sorted(self.slots_by_day.keys())

        self.aule = {a['id']: a for a in problema['aule']}
        self.docenti_indisponibili = {d['id']: set(d.get('indisponibili', [])) for d in problema['docenti']}
        self.classi_slot_attivi = {c['id']: set(c['slots_attivi']) for c in problema['classi']}

        # Laboratori (assegnati a mano): il docente non è disponibile in quello slot e l'aula è già occupata.
        self.occupazioni_fisse = problema.get('occupazioni_fisse', [])
        for occ in self.occupazioni_fisse:
            self.docenti_indisponibili.setdefault(occ['docente'], set()).add(occ['slot'])
        self.aula_fissi = defaultdict(int)
        for occ in self.occupazioni_fisse:
            if occ.get('aula') is not None:
                self.aula_fissi[(occ['aula'], occ['slot'])] += 1

        self.lezioni = problema['lezioni']
        self.diagnostica = []

        # letterale sempre falso, usato come default per "il docente non può
        # essere in questo slot" (nessuna variabile creata per lui lì).
        self.falso = model.NewBoolVar('falso')
        model.Add(self.falso == 0)

        # lezione -> {slot_id: BoolVar} per gli slot INIZIALI validi.
        self.start = {}
        # lezione -> {slot_id: BoolVar} per gli slot occupati (derivati da start + durata).
        self.occupato = {}

        self._crea_variabili_lezioni()
        self.docente_occ = self._crea_occupazione_docenti()
        self.disc_occ = self._crea_occupazione_disciplina_classe()

    def _slot_consecutivi_validi(self, classe_ids, durata):
        """Slot di partenza validi: stesso giorno, `durata` slot consecutivi,
        tutti attivi per tutte le classi coinvolte."""
        validi = []
        classi_attive = [self.classi_slot_attivi[c] for c in classe_ids]
        for giorno in self.giorni:
            slot_giorno = self.slots_by_day[giorno]
            for i in range(len(slot_giorno) - durata + 1):
                finestra = slot_giorno[i:i + durata]
                if all(all(s in attivi for s in finestra) for attivi in classi_attive):
                    validi.append(finestra)
        return validi

    def _crea_variabili_lezioni(self):
        for lez in self.lezioni:
            lid = lez['id']
            durata = lez.get('durata', 1)
            docenti_indisp = set()
            for d in lez['docenti']:
                docenti_indisp |= self.docenti_indisponibili.get(d, set())

            finestre = self._slot_consecutivi_validi(lez['classi'], durata)
            # esclude le finestre che cadono in indisponibilità di un docente della lezione.
            finestre = [f for f in finestre if not (set(f) & docenti_indisp)]

            bloccato = lez.get('bloccata_slot')
            if bloccato is not None:
                finestre = [f for f in finestre if f[0] == bloccato]

            start_vars = {}
            for finestra in finestre:
                s0 = finestra[0]
                start_vars[s0] = self.model.NewBoolVar(f'start_L{lid}_S{s0}')
            self.start[lid] = start_vars

            if start_vars:
                self.model.AddExactlyOne(start_vars.values())
            else:
                self.diagnostica.append(
                    f"Lezione {lid} (disciplina {lez['disciplina']}): nessuno slot disponibile "
                    "(indisponibilità docente o slot attivi della classe insufficienti)."
                )

            occ = defaultdict(list)
            for finestra in finestre:
                s0 = finestra[0]
                for s in finestra:
                    occ[s].append(start_vars[s0])
            self.occupato[lid] = {
                s: (lits[0] if len(lits) == 1 else reify_or(self.model, lits))
                for s, lits in occ.items()
            }

    def _crea_occupazione_docenti(self):
        docente_occ = defaultdict(lambda: defaultdict(list))
        for lez in self.lezioni:
            for s, var in self.occupato[lez['id']].items():
                for d in lez['docenti']:
                    docente_occ[d][s].append(var)

        risultato = defaultdict(dict)
        for docente_id, per_slot in docente_occ.items():
            for s, lits in per_slot.items():
                v = self.model.NewBoolVar(f'dococc_D{docente_id}_S{s}')
                self.model.Add(v == sum(lits))
                risultato[docente_id][s] = v
        return risultato

    def _crea_occupazione_disciplina_classe(self):
        disc_occ = defaultdict(lambda: defaultdict(list))
        for lez in self.lezioni:
            for classe_id in lez['classi']:
                for s, var in self.occupato[lez['id']].items():
                    disc_occ[(classe_id, lez['disciplina'])][s].append(var)

        risultato = defaultdict(dict)
        for chiave, per_slot in disc_occ.items():
            for s, lits in per_slot.items():
                v = self.model.NewBoolVar(f'discocc_{chiave[0]}_{chiave[1]}_S{s}')
                self.model.Add(v == sum(lits))
                risultato[chiave][s] = v
        return risultato

    def applica_vincoli_sistema(self):
        """H1+H5: ogni slot attivo di classe coperto da esattamente una lezione.
        H2: un docente al massimo in un posto per slot.
        H3+H7: capienza e tipo aula richiesto."""
        self._vincolo_copertura_classi()
        self._vincolo_un_posto_per_docente()
        self._vincolo_aule()

    def _vincolo_copertura_classi(self):
        classe_occ = defaultdict(lambda: defaultdict(list))
        for lez in self.lezioni:
            for classe_id in lez['classi']:
                for s, var in self.occupato[lez['id']].items():
                    classe_occ[classe_id][s].append(var)

        for classe_id, slot_attivi in self.classi_slot_attivi.items():
            for s in slot_attivi:
                lits = classe_occ[classe_id].get(s, [])
                self.model.Add(sum(lits) == 1)

    def _vincolo_un_posto_per_docente(self):
        for docente_id, per_slot in self.docente_occ.items():
            for s, v in per_slot.items():
                self.model.Add(v <= 1)

    def _vincolo_aule(self):
        disciplina_tipo_aula = self.problema.get('disciplina_tipo_aula', {})

        per_tipo = defaultdict(list)
        for lez in self.lezioni:
            tipo = lez.get('tipo_aula')
            if tipo:
                per_tipo[tipo].append(lez)

        self.aula_scelta = {}
        for tipo, lezioni_tipo in per_tipo.items():
            aule_tipo = [a for a in self.aule.values() if a['tipo'] == tipo]
            if not aule_tipo:
                for lez in lezioni_tipo:
                    self.diagnostica.append(
                        f"Lezione {lez['id']}: richiede aula di tipo '{tipo}' ma nessuna è censita."
                    )
                continue

            for lez in lezioni_tipo:
                scelta = {}
                for aula in aule_tipo:
                    scelta[aula['id']] = self.model.NewBoolVar(f"aula_L{lez['id']}_A{aula['id']}")
                self.model.AddExactlyOne(scelta.values())
                self.aula_scelta[lez['id']] = scelta

            for aula in aule_tipo:
                for s in self.slots:
                    occupanti = []
                    for lez in lezioni_tipo:
                        occ_s = self.occupato[lez['id']].get(s)
                        if occ_s is None:
                            continue
                        scelta_aula = self.aula_scelta[lez['id']][aula['id']]
                        z = self.model.NewBoolVar(f"z_L{lez['id']}_A{aula['id']}_S{s}")
                        self.model.Add(z <= occ_s)
                        self.model.Add(z <= scelta_aula)
                        self.model.Add(z >= occ_s + scelta_aula - 1)
                        occupanti.append(z)
                    if occupanti:
                        self.model.Add(sum(occupanti) <= max(0, aula['capacita'] - self.aula_fissi.get((aula['id'], s), 0)))
