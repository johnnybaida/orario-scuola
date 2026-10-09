// <button data-copia="#id">: copia negli appunti il testo del campo indicato (con ripiego per i browser senza clipboard API).
import { mostraToast } from './toast.js';

document.addEventListener('click', async (e) => {
    const pulsante = e.target.closest('[data-copia]');
    if (!pulsante) return;

    const campo = document.querySelector(pulsante.dataset.copia);
    try {
        await navigator.clipboard.writeText(campo.value ?? campo.textContent);
    } catch {
        campo.select();
        document.execCommand('copy');
    }
    mostraToast('Testo copiato negli appunti.', 'successo');
});
