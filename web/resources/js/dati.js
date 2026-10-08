// Esporta/importa dati: «Tutte» e «Nessuna» spuntano o tolgono la spunta alle tabelle del form.
document.querySelectorAll('[data-seleziona]').forEach((bottone) => {
    bottone.addEventListener('click', () => {
        bottone.closest('form').querySelectorAll('input[name="tabelle[]"]').forEach((c) => {
            c.checked = bottone.dataset.seleziona === 'tutte';
        });
    });
});
