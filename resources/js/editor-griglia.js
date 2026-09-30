// Drag & drop + cambio docente/materia per la griglia orario (vista classe).
// Nessuna libreria: HTML5 Drag and Drop API nativa. Vedi
// app/Services/Editor/EditorLezione per la validazione lato server
// (H1/H2/H5/H6/H7); qui ricarichiamo la pagina dopo ogni modifica riuscita,
// il server resta l'unica fonte di verità.
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function chiamaApi(url, method, corpo) {
    const risposta = await fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: corpo ? JSON.stringify(corpo) : undefined,
    });
    return { ok: risposta.ok, dati: await risposta.json() };
}

function inizializzaGriglia(griglia) {
    const urlLezioni = griglia.dataset.urlLezioni;
    let lezioneTrascinataId = null;

    griglia.addEventListener('dragstart', (evento) => {
        const carta = evento.target.closest('[data-lezione-id]');
        if (!carta) return;
        lezioneTrascinataId = carta.dataset.lezioneId;
        evento.dataTransfer.effectAllowed = 'move';
    });

    griglia.addEventListener('dragover', (evento) => {
        if (lezioneTrascinataId && evento.target.closest('[data-slot-id]')) {
            evento.preventDefault();
        }
    });

    griglia.addEventListener('drop', async (evento) => {
        const cella = evento.target.closest('[data-slot-id]');
        if (!cella || !lezioneTrascinataId) return;
        evento.preventDefault();

        const lezioneId = lezioneTrascinataId;
        lezioneTrascinataId = null;

        const { ok, dati } = await chiamaApi(`${urlLezioni}/${lezioneId}/sposta`, 'PATCH', { slot_id: cella.dataset.slotId })
            .catch(() => ({ ok: false, dati: { errori: ['Errore di rete.'] } }));

        if (ok) {
            window.location.reload();
        } else {
            alert((dati.errori || ['Spostamento non valido.']).join('\n'));
        }
    });

    griglia.addEventListener('click', async (evento) => {
        const bottone = evento.target.closest('.js-blocca-lezione');
        if (!bottone) return;

        const carta = bottone.closest('[data-lezione-id]');
        const { ok } = await chiamaApi(`${urlLezioni}/${carta.dataset.lezioneId}/blocca`, 'POST');
        if (ok) window.location.reload();
    });

    griglia.addEventListener('change', async (evento) => {
        const select = evento.target.closest('.js-cambia-cattedra');
        if (!select) return;

        const valorePrecedente = [...select.options].find((o) => o.defaultSelected)?.value;
        const { ok, dati } = await chiamaApi(`${urlLezioni}/${select.dataset.lezioneId}/cattedra`, 'PATCH', { cattedra_id: select.value })
            .catch(() => ({ ok: false, dati: { errori: ['Errore di rete.'] } }));

        if (ok) {
            if (dati.avvisi && dati.avvisi.length) alert(dati.avvisi.join('\n'));
            window.location.reload();
        } else {
            alert((dati.errori || ['Modifica non valida.']).join('\n'));
            select.value = valorePrecedente;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const griglia = document.querySelector('#griglia-orario');
    if (!griglia || griglia.dataset.editabile !== '1') return;
    inizializzaGriglia(griglia);
});
