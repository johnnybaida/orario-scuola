// Mostra solo i campi `parametri` del tipo di vincolo selezionato.
// No-op su ogni pagina diversa dal form vincoli; funziona anche nella modale (eventi delegati).
function aggiornaCampiParametri() {
    const select = document.querySelector('#tipo');
    if (!select || !document.querySelector('[data-parametri-per]')) return;

    document.querySelectorAll('[data-parametri-per]').forEach((gruppo) => {
        gruppo.hidden = gruppo.dataset.parametriPer !== select.value;
    });
}

document.addEventListener('DOMContentLoaded', aggiornaCampiParametri);
document.addEventListener('modale:caricata', aggiornaCampiParametri);
document.addEventListener('change', (e) => {
    if (e.target.matches('#tipo')) aggiornaCampiParametri();
});
