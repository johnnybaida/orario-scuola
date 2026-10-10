// Cattedra con docente CLIL: scegliendo il docente, le ore CLIL passano da 0 a 1 e la compresenza si spunta; togliendo il docente le ore tornano a 0.
// Vale nella scheda della classe (righe delle cattedre) e nel form della singola cattedra.
document.addEventListener('change', (evento) => {
    const select = evento.target.closest('select[name*="docente_clil_id"]');
    if (!select) return;

    const gruppo = select.closest('[data-riga]') ?? select.closest('form');
    const ore = gruppo?.querySelector('input[name*="ore_clil"]');
    const compresenza = gruppo?.querySelector('input[type="checkbox"][name*="compresenza"]');
    if (select.value) {
        if (ore && !(Number(ore.value) > 0)) ore.value = 1;
        if (compresenza) compresenza.checked = true;
    } else if (ore) {
        ore.value = 0;
    }
});
