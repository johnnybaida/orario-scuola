// Notifiche a comparsa (toast) che spariscono dopo 10 secondi; il tempo si ferma con mouse o focus sopra, × per chiudere.
// Il server mette i messaggi in <div data-flash="successo|errore|avviso" hidden><span>…</span></div> (vedi layouts/app);
// il JS usa mostraToast(). I toast si impilano in verticale (il più recente in cima). Con una modale aperta il toast va dentro la modale, altrimenti resterebbe sotto di lei.
const DURATA_MS = 10000;
const STILI = {
    successo: 'border-l-green-600',
    errore: 'border-l-red-600',
    avviso: 'border-l-amber-500',
};

function contenitore() {
    const radice = document.querySelector('dialog[open]') ?? document.body;
    let box = radice.querySelector(':scope > [data-toasts]');
    if (!box) {
        box = document.createElement('div');
        box.dataset.toasts = '';
        box.className = 'fixed top-4 right-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2';
        radice.append(box);
    }
    return box;
}

export function mostraToast(messaggi, tipo = 'successo') {
    const righe = [].concat(messaggi).filter(Boolean);
    if (!righe.length) return;

    const toast = document.createElement('div');
    toast.setAttribute('role', tipo === 'errore' ? 'alert' : 'status');
    toast.className = `toast flex items-start gap-3 rounded-lg border border-gray-200 border-l-4 bg-white px-4 py-3 text-sm text-gray-800 shadow-lg ${STILI[tipo] ?? STILI.successo}`;

    const testo = document.createElement('div');
    testo.className = 'min-w-0 flex-1 space-y-1 break-words';
    righe.forEach((riga) => testo.append(Object.assign(document.createElement('p'), { textContent: riga })));

    const chiudi = document.createElement('button');
    chiudi.type = 'button';
    chiudi.setAttribute('aria-label', 'Chiudi la notifica');
    chiudi.className = 'text-xl leading-none text-gray-400 hover:text-gray-700 transition-colors cursor-pointer';
    chiudi.innerHTML = '&times;';

    toast.append(testo, chiudi);
    const box = contenitore();
    box.prepend(toast); // il più recente in cima, gli altri scendono

    let restante = DURATA_MS;
    let inizio;
    let timer;
    const rimuovi = () => { clearTimeout(timer); toast.remove(); if (!box.children.length) box.remove(); };
    const avvia = () => { inizio = Date.now(); timer = setTimeout(rimuovi, restante); };
    const ferma = () => { clearTimeout(timer); restante -= Date.now() - inizio; };
    toast.addEventListener('mouseenter', ferma);
    toast.addEventListener('mouseleave', avvia);
    toast.addEventListener('focusin', ferma);
    toast.addEventListener('focusout', avvia);
    chiudi.addEventListener('click', rimuovi);
    avvia();
}

document.addEventListener('DOMContentLoaded', () => {
    // Al caricamento si inserisce in ordine inverso, così in pagina i messaggi restano nell'ordine del documento.
    [...document.querySelectorAll('[data-flash]')].reverse().forEach((el) => {
        mostraToast([...el.children].map((c) => c.textContent.trim()), el.dataset.flash);
        el.remove();
    });
});
