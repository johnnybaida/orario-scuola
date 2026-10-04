// Avviso di nuova versione nella sidebar: chiede al server (che interroga GitHub, in cache) dopo il caricamento della pagina.
const contenitore = document.querySelector('[data-aggiornamenti]');

if (contenitore) {
    fetch(contenitore.dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((dati) => {
            if (!dati?.disponibile) return;
            const link = contenitore.querySelector('[data-aggiornamento-link]');
            link.href = dati.disponibile.url;
            link.textContent = `Disponibile la versione ${dati.disponibile.versione}`;
            link.hidden = false;
        })
        .catch(() => {}); // senza rete l'avviso semplicemente non compare
}
