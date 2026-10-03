// Apre in una <dialog> le pagine di creazione/modifica (link con `data-modale`).
// Si chiude con × (in alto a destra), "Annulla" o Esc, non con un click fuori. Piè di pagina fisso con un solo "Annulla" e un solo "Salva": Salva invia il form della modale o, se
// la pagina ne contiene più d'uno (es. dati + indisponibilità), tutti quelli modificati, in sequenza.
// Errori di validazione nella modale; dopo il salvataggio la modale si chiude e la pagina si ricarica (il flash resta in sessione).
const intestazioni = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
let dialog;

const corpo = () => dialog.querySelector('[data-corpo]');
const eEliminazione = (form) => form.querySelector('[name="_method"][value="DELETE"]');

function creaDialog() {
    dialog = document.createElement('dialog');
    dialog.className = 'relative m-auto w-full max-w-5xl max-h-[90vh] rounded-lg p-0 open:flex flex-col backdrop:bg-black/50';
    dialog.innerHTML = `<button type="button" data-chiudi aria-label="Chiudi" class="absolute right-4 top-3 z-10 text-2xl leading-none text-gray-500 hover:text-gray-900 transition-colors cursor-pointer">&times;</button>
        <div data-corpo class="px-6 py-6 overflow-y-auto flex-1"></div>
        <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-3">
            <span class="mr-auto text-xs text-gray-500"><span class="text-destructive">*</span> campo obbligatorio</span>
            <button type="button" data-chiudi class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">Annulla</button>
            <button type="button" data-salva class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer disabled:opacity-60">Salva</button>
        </div>`;
    dialog.addEventListener('click', (e) => {
        // Si chiude solo con ×, Annulla o Esc: un click fuori dalla modale non fa perdere i dati inseriti.
        if (e.target.closest('[data-chiudi]')) dialog.close();
        if (e.target.closest('[data-salva]')) salva();
    });
    dialog.addEventListener('input', (e) => segnaModificato(e));
    dialog.addEventListener('change', (e) => segnaModificato(e));
    dialog.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return; // es. conferma di eliminazione annullata
        e.preventDefault();
        eseguiInvio([e.target]);
    });
    document.body.append(dialog);
}

function segnaModificato(e) {
    const form = e.target.closest('form');
    if (form) form.dataset.modificato = '1';
}

function mostraErrori(messaggi) {
    corpo().querySelector('[data-errori]')?.remove();
    const box = document.createElement('div');
    box.dataset.errori = '';
    box.className = 'mb-4 rounded bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm';
    box.innerHTML = '<ul class="list-disc list-inside space-y-1"></ul>';
    messaggi.forEach((m) => box.firstChild.append(Object.assign(document.createElement('li'), { textContent: m })));
    corpo().prepend(box);
    corpo().scrollTo(0, 0);
}

async function carica(url) {
    const risposta = await fetch(url, { headers: intestazioni });
    corpo().innerHTML = await risposta.text();
    // I pulsanti di invio interni sono sostituiti dal "Salva" del piè di pagina (restano quelli di eliminazione riga).
    corpo().querySelectorAll('form').forEach((form) => {
        if (!eEliminazione(form)) form.querySelectorAll('button[type="submit"]').forEach((b) => { b.hidden = true; });
    });
    if (!dialog.open) dialog.showModal();
    document.dispatchEvent(new CustomEvent('modale:caricata', { detail: dialog }));
}

function salva() {
    const forms = [...corpo().querySelectorAll('form')].filter((f) => !eEliminazione(f));
    const da = forms.length === 1 ? forms : forms.filter((f) => f.dataset.modificato);
    if (!da.length) return dialog.close();
    if (!da.every((f) => f.reportValidity())) return;
    eseguiInvio(da);
}

async function eseguiInvio(forms) {
    const bottone = dialog.querySelector('[data-salva]');
    bottone.disabled = true;
    try {
        let ultima;
        for (const form of forms) {
            ultima = await fetch(form.action, {
                method: 'POST', body: new FormData(form), headers: intestazioni, redirect: 'manual',
            });
            if (ultima.status === 422) return mostraErrori(Object.values((await ultima.json()).errors).flat());
            if (!(ultima.ok || ultima.type === 'opaqueredirect')) {
                return mostraErrori([`Operazione non riuscita (codice ${ultima.status}).`]);
            }
        }
        location.reload();
    } finally {
        bottone.disabled = false;
    }
}

document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[data-modale]');
    if (!link) return;
    e.preventDefault();
    if (!dialog) creaDialog();
    await carica(link.href);
});
