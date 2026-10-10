// Form di modifica con righe ripetibili (<x-righe-ripetibili>): aggiunta/rimozione righe, totali live
// e rientri pomeridiani della classe che spuntano le ore del pomeriggio nella griglia degli slot.
let contatore = 0;

// Select [data-univoca] in righe ripetibili: un valore già scelto in un'altra riga si disattiva nelle altre (con nota),
// e "+ Aggiungi" si spegne quando non resta nessun valore libero.
function aggiornaUnivoci() {
    document.querySelectorAll('[data-ripetibile]').forEach((radice) => {
        const modello = radice.querySelector('template[data-modello]').content.querySelector('select[data-univoca]');
        if (!modello) return;

        const selects = [...radice.querySelectorAll('[data-righe] select[data-univoca]')];
        const usati = selects.map((s) => s.value);
        selects.forEach((s) => [...s.options].forEach((o) => {
            o.dataset.testo ??= o.textContent;
            o.disabled = o.value !== s.value && usati.includes(o.value);
            o.textContent = o.disabled ? `${o.dataset.testo} (già inserita)` : o.dataset.testo;
        }));

        const esaurito = [...modello.options].every((o) => usati.includes(o.value));
        const aggiungi = radice.querySelector('[data-aggiungi]');
        if (!aggiungi.hasAttribute('data-bloccato')) aggiungi.disabled = esaurito;
        radice.querySelector('[data-esaurito]')?.toggleAttribute('hidden', !esaurito || aggiungi.hasAttribute('data-bloccato'));
    });
}

// Somma gli input data-somma="g" e, come ore (60 minuti = 1), le opzioni scelte nelle select data-somma-minuti="g" (data-minuti).
const somma = (form, gruppo) => Math.round((
    [...form.querySelectorAll(`input[data-somma="${gruppo}"]`)]
        .reduce((totale, input) => totale + (Number(input.value) || 0), 0)
    + [...form.querySelectorAll(`select[data-somma-minuti="${gruppo}"]`)].reduce((totale, s) => totale + (Number(s.selectedOptions[0]?.dataset.minuti) || 0) / 60, 0)
) * 100) / 100;

const formatta = (n) => n.toLocaleString('it-IT', { maximumFractionDigits: 2 });

// [data-totale="g"] mostra la somma degli input data-somma="g"; con data-riferimento="#id" o "somma:altro-gruppo" mostra "somma / riferimento".
function ricalcola() {
    document.querySelectorAll('[data-totale]').forEach((el) => {
        const form = el.closest('form') ?? document;
        // «a+b»: somma di più gruppi (es. ore delle cattedre + ore di mensa del quadro)
        const totale = Math.round(el.dataset.totale.split('+').reduce((t, g) => t + somma(form, g), 0) * 100) / 100;
        const rif = el.dataset.riferimento ?? '';
        let riferimento = null;
        if (rif.startsWith('somma:')) riferimento = somma(form, rif.slice(6));
        else if (rif) {
            const campo = form.querySelector(rif);
            riferimento = Number((campo.value ?? campo.textContent).trim());
        }
        el.textContent = riferimento === null ? formatta(totale) : `${formatta(totale)} / ${formatta(riferimento)}`;
        el.classList.toggle('text-amber-700', riferimento !== null && totale !== riferimento);
    });
}

document.addEventListener('click', (e) => {
    const aggiungi = e.target.closest('[data-aggiungi]');
    const rimuovi = e.target.closest('[data-rimuovi]');
    if (aggiungi) {
        const radice = aggiungi.closest('[data-ripetibile]');
        const modello = radice.querySelector('template[data-modello]').innerHTML;
        const righe = radice.querySelector('[data-righe]');
        righe.insertAdjacentHTML('beforeend', modello.replaceAll('__I__', `n${contatore++}`));
        // La nuova riga parte dal primo valore ancora libero.
        const usati = [...righe.querySelectorAll('select[data-univoca]')].slice(0, -1).map((s) => s.value);
        const nuova = righe.lastElementChild.querySelector('select[data-univoca]');
        const libero = nuova && [...nuova.options].find((o) => !usati.includes(o.value));
        if (libero) nuova.value = libero.value;
    }
    if (rimuovi) rimuovi.closest('[data-riga]').remove();
    if (aggiungi || rimuovi) {
        ricalcola();
        aggiornaUnivoci();
    }
});

document.addEventListener('input', ricalcola);
document.addEventListener('change', aggiornaUnivoci);
document.addEventListener('DOMContentLoaded', () => { ricalcola(); aggiornaUnivoci(); });
document.addEventListener('modale:caricata', () => { ricalcola(); aggiornaUnivoci(); });

document.addEventListener('change', (e) => {
    const rientro = e.target.closest('[data-rientro]');
    if (!rientro) return;
    rientro.form.querySelectorAll(`input[data-pomeridiano][data-giorno="${rientro.value}"]`)
        .forEach((slot) => { slot.checked = rientro.checked; });
});
