// Form di modifica con righe ripetibili (<x-righe-ripetibili>): aggiunta/rimozione righe, totali live
// e rientri pomeridiani della classe che spuntano le ore del pomeriggio nella griglia degli slot.
let contatore = 0;

const somma = (form, gruppo) => [...form.querySelectorAll(`input[data-somma="${gruppo}"]`)]
    .reduce((totale, input) => totale + (Number(input.value) || 0), 0);

// [data-totale="g"] mostra la somma degli input data-somma="g"; con data-riferimento="#id" o "somma:altro-gruppo" mostra "somma / riferimento".
function ricalcola() {
    document.querySelectorAll('[data-totale]').forEach((el) => {
        const form = el.closest('form') ?? document;
        const totale = somma(form, el.dataset.totale);
        const rif = el.dataset.riferimento ?? '';
        let riferimento = null;
        if (rif.startsWith('somma:')) riferimento = somma(form, rif.slice(6));
        else if (rif) {
            const campo = form.querySelector(rif);
            riferimento = Number((campo.value ?? campo.textContent).trim());
        }
        el.textContent = riferimento === null ? totale : `${totale} / ${riferimento}`;
        el.classList.toggle('text-amber-700', riferimento !== null && totale !== riferimento);
    });
}

document.addEventListener('click', (e) => {
    const aggiungi = e.target.closest('[data-aggiungi]');
    const rimuovi = e.target.closest('[data-rimuovi]');
    if (aggiungi) {
        const radice = aggiungi.closest('[data-ripetibile]');
        const modello = radice.querySelector('template[data-modello]').innerHTML;
        radice.querySelector('[data-righe]').insertAdjacentHTML('beforeend', modello.replaceAll('__I__', `n${contatore++}`));
    }
    if (rimuovi) rimuovi.closest('[data-riga]').remove();
    if (aggiungi || rimuovi) ricalcola();
});

document.addEventListener('input', ricalcola);
document.addEventListener('DOMContentLoaded', ricalcola);
document.addEventListener('modale:caricata', ricalcola);

document.addEventListener('change', (e) => {
    const rientro = e.target.closest('[data-rientro]');
    if (!rientro) return;
    rientro.form.querySelectorAll(`input[data-pomeridiano][data-giorno="${rientro.value}"]`)
        .forEach((slot) => { slot.checked = rientro.checked; });
});
