// Mostra solo i campi `parametri` del tipo di vincolo selezionato.
// No-op su ogni pagina diversa dal form vincoli (nessun elemento trovato).
function aggiornaCampiParametri() {
    const select = document.querySelector('#tipo');
    if (!select) return;

    const gruppi = document.querySelectorAll('[data-parametri-per]');
    gruppi.forEach((gruppo) => {
        gruppo.hidden = gruppo.dataset.parametriPer !== select.value;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const select = document.querySelector('#tipo');
    if (!select) return;

    aggiornaCampiParametri();
    select.addEventListener('change', aggiornaCampiParametri);
});
