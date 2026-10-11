// Interruttore attivo/disattivo sulle card dei vincoli: salva subito; se fallisce rimette com'era.
import { mostraToast } from './toast.js';

document.addEventListener('change', async (e) => {
    const interruttore = e.target.closest('.js-vincolo-attivo');
    if (!interruttore) return;

    const attivo = interruttore.checked;
    const card = interruttore.closest('[data-vincolo]');
    try {
        const r = await fetch(interruttore.dataset.url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ attivo }),
        });
        if (!r.ok) throw new Error();
        card.classList.toggle('opacity-60', !attivo);
        mostraToast(attivo ? 'Vincolo attivato.' : 'Vincolo disattivato.');
    } catch {
        interruttore.checked = !attivo;
        mostraToast('Non è stato possibile cambiare il vincolo.', 'errore');
    }
});
