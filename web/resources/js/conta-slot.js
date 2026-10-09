// Scheda classe: conta gli slot attivi spuntati e confronta con le ore di lezione attese (quadro meno le ore senza ora, come la mensa).
function aggiorna() {
    const riquadro = document.querySelector('[data-conta-slot]');
    if (!riquadro) return;
    const n = document.querySelectorAll('input[name="slot_ids[]"]:checked').length;
    riquadro.querySelector('[data-conta-slot-n]').textContent = n;
    const ok = n === Number(riquadro.dataset.attesi);
    riquadro.classList.toggle('border-amber-300', !ok);
    riquadro.classList.toggle('bg-amber-50', !ok);
    riquadro.classList.toggle('border-gray-200', ok);
    riquadro.classList.toggle('bg-gray-50', ok);
}

document.addEventListener('change', (e) => { if (e.target.matches('input[name="slot_ids[]"]')) aggiorna(); else if (e.target.matches('[data-rientro]')) setTimeout(aggiorna); });   // i rientri spuntano gli slot in un altro modulo
document.addEventListener('DOMContentLoaded', aggiorna);
