// Avviso di nuova versione accanto al pulsante Aiuto. Il controllo (che interroga GitHub, senza cache) si fa solo aprendo la dashboard,
// cioè dopo il login; l'esito resta nella sessione del browser e le altre pagine lo mostrano senza richiamare il server.
const contenitore = document.querySelector('[data-aggiornamenti]');
const CHIAVE = 'aggiornamento-disponibile';

function mostra(disponibile) {
    if (!disponibile) return;
    const link = contenitore.querySelector('[data-aggiornamento-link]');
    link.href = disponibile.url;
    link.textContent = `Disponibile la versione ${disponibile.versione}`;
    link.hidden = false;
}

if (contenitore) {
    const salvato = sessionStorage.getItem(CHIAVE);
    if (salvato !== null && !('controlla' in contenitore.dataset)) {
        mostra(JSON.parse(salvato));
    } else if ('controlla' in contenitore.dataset) {
        fetch(contenitore.dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((r) => (r.ok ? r.json() : null))
            .then((dati) => {
                if (!dati) return;
                sessionStorage.setItem(CHIAVE, JSON.stringify(dati.disponibile));
                mostra(dati.disponibile);
            })
            .catch(() => {}); // senza rete l'avviso semplicemente non compare
    }
}
