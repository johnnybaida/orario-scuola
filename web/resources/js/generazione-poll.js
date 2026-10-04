// Polling dello stato di una generazione in corso (§7.6: avanzamento via polling).
const STATI_FINALI = ['completata', 'infattibile', 'fallita', 'annullata'];

function aggiorna(container) {
    fetch(container.dataset.url, { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((dati) => {
            container.querySelector('[data-campo="stato"]').textContent = dati.stato;
            container.querySelector('[data-campo="barra"]').style.width = `${dati.progresso}%`;

            if (STATI_FINALI.includes(dati.stato)) {
                window.location.reload();
                return;
            }

            setTimeout(() => aggiorna(container), 2000);
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('#stato-generazione');
    if (!container) return;
    if (STATI_FINALI.includes(container.dataset.stato)) return;

    setTimeout(() => aggiorna(container), 2000);
});
