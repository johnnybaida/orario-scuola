// Pagina Mensa: in ogni cella (classe + giorno) c'è sempre una select vuota in più per aggiungere un altro docente.
// Le select sono «select di ricerca» (select-ricerca.js): la select vera è nascosta e ha accanto il pulsante.
const pulsanteDi = (select) => (select.nextElementSibling?.tagName === 'BUTTON' ? select.nextElementSibling : null);

document.addEventListener('change', (e) => {
    const cella = e.target.closest('[data-cella-mensa]');
    if (!cella || !e.target.matches('select')) return;

    const selects = [...cella.querySelectorAll('select')];
    const vuote = selects.filter((s) => s.value === '');
    if (vuote.length === 0) {
        const nuova = selects[selects.length - 1].cloneNode(true);
        nuova.value = '';
        nuova.hidden = false;
        delete nuova.dataset.ricercaPronta;   // da ri-migliorare
        cella.appendChild(nuova);
        document.dispatchEvent(new Event('ricerca:aggiorna'));
    } else if (vuote.length > 1) {
        vuote.slice(1).forEach((s) => { pulsanteDi(s)?.remove(); s.remove(); }); // ne basta una vuota
    }
});
