// Tooltip <x-info>: posizionato in coordinate dello schermo, sotto l'icona (sopra se manca spazio) e dentro la finestra,
// così non viene tagliato dalle tabelle con overflow né dipende dalla larghezza della colonna.
const MARGINE = 8;
let aperto = null;

function posiziona(info) {
    const icona = info.querySelector('[role="img"]');
    const tip = info.querySelector('[role="tooltip"]');
    if (!icona || !tip) return;
    const r = icona.getBoundingClientRect();
    const larghezza = tip.offsetWidth;
    const altezza = tip.offsetHeight;
    const sinistra = Math.max(MARGINE, Math.min(r.left + r.width / 2 - larghezza / 2, window.innerWidth - larghezza - MARGINE));
    const sotto = r.bottom + 6;
    tip.style.left = `${sinistra}px`;
    tip.style.top = `${sotto + altezza > window.innerHeight - MARGINE ? Math.max(MARGINE, r.top - altezza - 6) : sotto}px`;
}

function mostra(e) {
    const info = e.target.closest?.('[data-info]');
    if (!info) return;
    aperto = info;
    posiziona(info);
}

document.addEventListener('mouseover', mostra);
document.addEventListener('focusin', mostra);
window.addEventListener('scroll', () => aperto && posiziona(aperto), true);
window.addEventListener('resize', () => aperto && posiziona(aperto));
