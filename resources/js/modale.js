// Apre in una <dialog> le pagine di creazione/modifica (link con `data-modale`) e invia i form
// via fetch: errori di validazione nella modale, successo = reload della pagina (il flash resta in sessione).
// Con `data-modale="resta"` la modale resta aperta dopo ogni salvataggio (si ricarica il suo contenuto,
// o si apre la pagina verso cui il server reindirizza) e la pagina sotto si aggiorna alla chiusura.
const intestazioni = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
let dialog;
let resta = false;
let urlCorrente;
let modificato = false;

function creaDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'm-auto w-full max-w-5xl max-h-[90vh] rounded-lg p-0 backdrop:bg-black/50';
    dialog.innerHTML = `<div class="flex justify-end px-4 pt-3">
        <button type="button" data-chiudi aria-label="Chiudi" class="text-gray-500 hover:text-gray-900 text-xl leading-none cursor-pointer">&times;</button>
    </div><div data-corpo class="px-6 pb-6"></div>`;
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog || e.target.closest('[data-chiudi]')) dialog.close();
    });
    dialog.addEventListener('close', () => {
        if (modificato) location.reload();
    });
    dialog.addEventListener('submit', invia);
    document.body.append(dialog);
}

function mostraErrori(messaggi) {
    const corpo = dialog.querySelector('[data-corpo]');
    corpo.querySelector('[data-errori]')?.remove();
    const box = document.createElement('div');
    box.dataset.errori = '';
    box.className = 'mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm';
    box.innerHTML = '<ul class="list-disc list-inside space-y-1"></ul>';
    messaggi.forEach((m) => box.firstChild.append(Object.assign(document.createElement('li'), { textContent: m })));
    corpo.prepend(box);
    dialog.scrollTo(0, 0);
}

async function carica(url) {
    urlCorrente = url;
    const risposta = await fetch(url, { headers: intestazioni });
    dialog.querySelector('[data-corpo]').innerHTML = await risposta.text();
    if (!dialog.open) dialog.showModal();
    document.dispatchEvent(new CustomEvent('modale:caricata', { detail: dialog }));
}

async function invia(e) {
    if (e.defaultPrevented) return; // es. conferma di eliminazione annullata
    e.preventDefault();
    const form = e.target;
    const risposta = await fetch(form.action, {
        method: 'POST', body: new FormData(form), headers: intestazioni, redirect: resta ? 'follow' : 'manual',
    });
    if (risposta.status === 422) return mostraErrori(Object.values((await risposta.json()).errors).flat());
    if (!(risposta.ok || risposta.type === 'opaqueredirect')) {
        return mostraErrori([`Operazione non riuscita (codice ${risposta.status}).`]);
    }
    if (!resta) return location.reload();

    modificato = true;
    // Redirect verso un'altra pagina (es. dopo la creazione si passa alla modifica): la mostriamo; altrimenti ricarichiamo la corrente.
    const destinazione = risposta.redirected && risposta.url !== location.href ? risposta.url : urlCorrente;
    await carica(destinazione);
}

document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[data-modale]');
    if (!link) return;
    e.preventDefault();
    if (!dialog) creaDialog();
    resta = link.dataset.modale === 'resta';
    modificato = false;
    await carica(link.href);
});
