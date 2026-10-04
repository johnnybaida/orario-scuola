// Drag & drop + cambio docente/materia per la griglia orario (vista classe).
// Nessuna libreria: HTML5 Drag and Drop API nativa. Vedi
// app/Services/Editor/EditorLezione per la validazione lato server
// (H1/H2/H5/H6/H7). Ogni tentativo (riuscito o no) viene registrato come
// AvvisoOrario lato server e mostrato nel pannello persistente della
// pagina: qui ricarichiamo sempre, niente alert() che si perdono al click.
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

        await chiamaApi(`${urlLezioni}/${lezioneId}/sposta`, 'PATCH', { slot_id: cella.dataset.slotId }).catch(() => null);
        window.location.reload();
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

        await chiamaApi(`${urlLezioni}/${select.dataset.lezioneId}/cattedra`, 'PATCH', { cattedra_id: select.value }).catch(() => null);
        window.location.reload();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const griglia = document.querySelector('#griglia-orario');
    if (!griglia || griglia.dataset.editabile !== '1') return;
    inizializzaGriglia(griglia);
});

// Scorciatoie: Ctrl/Cmd+Z annulla, Ctrl/Cmd+Maiusc+Z (o Ctrl+Y) ripete; solo nella griglia modificabile e non mentre si scrive in un campo.
document.addEventListener('keydown', (evento) => {
    const griglia = document.querySelector('#griglia-orario');
    if (!griglia || griglia.dataset.editabile !== '1') return;
    if (!(evento.ctrlKey || evento.metaKey) || evento.target.closest('input, textarea, select, [contenteditable]')) return;

    const tasto = evento.key.toLowerCase();
    const ripeti = (tasto === 'z' && evento.shiftKey) || tasto === 'y';
    const annulla = tasto === 'z' && !evento.shiftKey;
    if (!ripeti && !annulla) return;

    const pulsante = document.querySelector(ripeti ? '#form-ripeti button' : '#form-annulla button');
    if (!pulsante || pulsante.disabled) return;
    evento.preventDefault();
    pulsante.form.requestSubmit();
});
