// Pulsante «Schermo intero» (accanto ad Aiuto): mette tutta la pagina a schermo intero e la riporta com'era. Si nasconde dove il
// browser non supporta l'API (es. alcuni browser mobili). Esc esce, come sempre; l'icona e il testo seguono lo stato.
const pulsante = () => document.querySelector('[data-schermo-intero]');

function aggiorna() {
    const p = pulsante();
    if (!p) return;
    const pieno = Boolean(document.fullscreenElement);
    p.querySelector('[data-icona-espandi]').hidden = pieno;
    p.querySelector('[data-icona-riduci]').hidden = !pieno;
    // Il browser esce da solo dallo schermo intero «dell'app» a ogni cambio di pagina (limite delle pagine web): per restarci navigando serve quello del browser.
    const testo = pieno ? 'Esci dallo schermo intero' : 'Schermo intero (cambiando pagina si esce: per restarci usa F11, su Mac Ctrl+Cmd+F)';
    p.title = testo;
    p.setAttribute('aria-label', testo);
}

document.addEventListener('DOMContentLoaded', () => {
    const p = pulsante();
    if (p && document.fullscreenEnabled) p.hidden = false;
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-schermo-intero]')) return;
    if (document.fullscreenElement) document.exitFullscreen();
    else document.documentElement.requestFullscreen().catch(() => {});
});

document.addEventListener('fullscreenchange', aggiorna);
