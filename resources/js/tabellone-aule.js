// Tabellone per aula: con l'orario in bozza le lezioni si trascinano tra le celle «aula × ora».
// Stessa ora, altra aula = cambio di aula; altra ora = spostamento (l'aula scelta è quella della cella, se disponibile).
// Gli esiti restano nel registro delle modifiche in cima alla pagina (come nella griglia della classe).
import { chiamaApi, coloraCelle, leggiProvvisorio, pulisciCelle, salvaProvvisorio } from './destinazioni.js';

const tabella = document.querySelector('#tabellone[data-editabile="1"]');

if (tabella) {
    const urlLezioni = tabella.dataset.urlLezioni;
    const celle = () => tabella.querySelectorAll('td[data-aula-id][data-slot-id]');
    let trascinata = null; // { id, slot }

    const interruttore = document.querySelector('#conflitti-provvisori');
    if (interruttore) {
        interruttore.checked = leggiProvvisorio();
        interruttore.addEventListener('change', () => salvaProvvisorio(interruttore.checked));
    }
    const provvisorio = () => Boolean(interruttore?.checked);

    tabella.addEventListener('dragstart', (evento) => {
        const carta = evento.target.closest('[data-lezione-id]');
        if (!carta) return;
        trascinata = { id: carta.dataset.lezioneId, slot: carta.dataset.slotId };
        evento.dataTransfer.effectAllowed = 'move';

        const { id } = trascinata;
        chiamaApi(`${urlLezioni}/${id}/destinazioni-aule`, 'GET')
            .then(({ ok, dati }) => { if (ok && trascinata?.id === id) coloraCelle(celle(), dati, provvisorio(), (c) => `${c.dataset.aulaId}-${c.dataset.slotId}`); })
            .catch(() => {}); // senza suggerimenti si può comunque trascinare
    });

    tabella.addEventListener('dragend', () => { pulisciCelle(celle()); trascinata = null; });

    tabella.addEventListener('dragover', (evento) => {
        if (trascinata && evento.target.closest('td[data-aula-id][data-slot-id]')) evento.preventDefault();
    });

    tabella.addEventListener('drop', async (evento) => {
        const cella = evento.target.closest('td[data-aula-id][data-slot-id]');
        if (!cella || !trascinata) return;
        evento.preventDefault();

        const { id, slot } = trascinata;
        trascinata = null;
        const corpo = { provvisorio: provvisorio() };
        if (cella.dataset.slotId === slot) {
            await chiamaApi(`${urlLezioni}/${id}/aula`, 'PATCH', { ...corpo, aula_id: cella.dataset.aulaId }).catch(() => null);
        } else {
            await chiamaApi(`${urlLezioni}/${id}/sposta`, 'PATCH', { ...corpo, slot_id: cella.dataset.slotId, aula_id: cella.dataset.aulaId }).catch(() => null);
        }
        window.location.reload();
    });
}
