// Modifica trascinando nelle viste con i riquadri delle lezioni (tabellone per classe e per aula, vista docente, vista aula).
// La tabella dichiara `data-modifica data-editabile="1" data-url-lezioni` e un modo (`data-modo`):
//  - «slot»: la lezione cambia ora (tabellone per classe: solo lungo la riga della sua classe; vista docente);
//  - «aula»: celle «aula × ora» con data-aula-id: stessa ora = cambio di aula, altra ora = spostamento nell'aula scelta.
// Gli esiti restano nel registro delle modifiche in cima alla pagina (come nella griglia della classe).
import { chiamaApi, coloraCelle, leggiProvvisorio, pulisciCelle, salvaProvvisorio } from './destinazioni.js';

const tabella = document.querySelector('[data-modifica][data-editabile="1"]');

if (tabella) {
    const urlLezioni = tabella.dataset.urlLezioni;
    const perAula = tabella.dataset.modo === 'aula';
    const selettoreCelle = perAula ? 'td[data-aula-id][data-slot-id]' : 'td[data-slot-id]';
    const celle = () => tabella.querySelectorAll(selettoreCelle);
    let trascinata = null; // { id, slot, aula, riga }

    const interruttore = document.querySelector('#conflitti-provvisori');
    if (interruttore) {
        interruttore.checked = leggiProvvisorio();
        interruttore.addEventListener('change', () => salvaProvvisorio(interruttore.checked));
    }
    const provvisorio = () => Boolean(interruttore?.checked);
    // Nel tabellone per classe una lezione resta nella sua classe: le altre righe non sono destinazioni valide.
    const stessaRiga = (cella) => cella.dataset.riga === trascinata?.riga;

    tabella.addEventListener('dragstart', (evento) => {
        const carta = evento.target.closest('[data-lezione-id]');
        if (!carta) return;
        const origine = carta.closest('td');
        trascinata = { id: carta.dataset.lezioneId, slot: carta.dataset.slotId, aula: origine?.dataset.aulaId, riga: origine?.dataset.riga };
        evento.dataTransfer.effectAllowed = 'move';

        const { id } = trascinata;
        chiamaApi(`${urlLezioni}/${id}/${perAula ? 'destinazioni-aule' : 'destinazioni'}`, 'GET')
            .then(({ ok, dati }) => {
                if (!ok || trascinata?.id !== id) return;
                const valide = [...celle()].filter((c) => perAula || stessaRiga(c));
                coloraCelle(valide, dati, provvisorio(), (c) => (perAula ? `${c.dataset.aulaId}-${c.dataset.slotId}` : c.dataset.slotId));
            })
            .catch(() => {}); // senza suggerimenti si può comunque trascinare
    });

    tabella.addEventListener('dragend', () => { pulisciCelle(celle()); trascinata = null; });

    tabella.addEventListener('dragover', (evento) => {
        const cella = evento.target.closest(selettoreCelle);
        if (trascinata && cella && (perAula || stessaRiga(cella))) evento.preventDefault();
    });

    tabella.addEventListener('drop', async (evento) => {
        const cella = evento.target.closest(selettoreCelle);
        if (!cella || !trascinata || !(perAula || stessaRiga(cella))) return;
        evento.preventDefault();

        const { id, slot, aula } = trascinata;
        trascinata = null;
        if (cella.dataset.slotId === slot && (!perAula || cella.dataset.aulaId === aula)) return; // rilasciata dove stava: niente da fare
        const corpo = { provvisorio: provvisorio() };
        if (perAula && cella.dataset.slotId === slot) {
            await chiamaApi(`${urlLezioni}/${id}/aula`, 'PATCH', { ...corpo, aula_id: cella.dataset.aulaId }).catch(() => null);
        } else {
            await chiamaApi(`${urlLezioni}/${id}/sposta`, 'PATCH', { ...corpo, slot_id: cella.dataset.slotId, ...(perAula ? { aula_id: cella.dataset.aulaId } : {}) }).catch(() => null);
        }
        window.location.reload();
    });
}
