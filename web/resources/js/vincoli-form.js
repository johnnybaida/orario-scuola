// Mostra solo i campi `parametri` del tipo di vincolo selezionato.
// No-op su ogni pagina diversa dal form vincoli; funziona anche nella modale (eventi delegati).
function aggiornaCampiParametri() {
    const select = document.querySelector('#tipo');
    if (!select || !document.querySelector('[data-parametri-per]')) return;

    document.querySelectorAll('[data-parametri-per]').forEach((gruppo) => {
        gruppo.hidden = gruppo.dataset.parametriPer !== select.value;
        gruppo.querySelectorAll('input, select, textarea').forEach((c) => { c.disabled = gruppo.hidden; });
    });
}

document.addEventListener('DOMContentLoaded', aggiornaCampiParametri);
document.addEventListener('modale:caricata', aggiornaCampiParametri);
document.addEventListener('change', (e) => {
    if (e.target.matches('#tipo')) aggiornaCampiParametri();
});

// Discipline: «Tutte» / «Nessuna»; slot di D6: «Tutte» spunta (o toglie) l'intero giorno.
document.addEventListener('click', (e) => {
    const sel = e.target.closest('[data-seleziona]');
    if (sel) {
        const sceglie = sel.dataset.seleziona === 'tutte';
        sel.closest('[data-discipline-vincolo]').querySelectorAll('input[type="checkbox"]').forEach((c) => { c.checked = sceglie; });
        return;
    }
    const giorno = e.target.closest('[data-giorno-tutto]');
    if (giorno) {
        const caselle = [...giorno.closest('div').querySelectorAll('input[type="checkbox"]')];
        const tutte = caselle.every((c) => c.checked);
        caselle.forEach((c) => { c.checked = !tutte; });
    }
});
