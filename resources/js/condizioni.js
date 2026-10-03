// Controlli condizionati da un altro campo: <div data-attiva-se="#campo=valore|altro">. Quando la condizione non
// è vera i controlli interni sono disabilitati (e quindi non inviati) e compare l'icona [data-info] che spiega come attivarli.
function valuta() {
    document.querySelectorAll('[data-attiva-se]').forEach((el) => {
        const [selettore, valori] = el.dataset.attivaSe.split('=');
        const campo = (el.closest('form') ?? document).querySelector(selettore);
        const attivo = Boolean(campo) && valori.split('|').includes(campo.value);

        el.querySelectorAll('input, select, textarea').forEach((c) => { c.disabled = !attivo; });
        el.classList.toggle('opacity-60', !attivo);
        el.querySelector('[data-info]')?.toggleAttribute('hidden', attivo);
    });
}

document.addEventListener('DOMContentLoaded', valuta);
document.addEventListener('modale:caricata', valuta);
document.addEventListener('change', valuta);
