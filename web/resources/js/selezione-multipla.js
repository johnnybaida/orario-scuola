import { mostraToast } from './toast.js';

// Checkbox di riga (.js-sel, value = URL di eliminazione) + barra azioni <x-barra-selezione>.
// Elimina riutilizzando le rotte destroy esistenti, una richiesta per riga.
const selezionate = () => [...document.querySelectorAll('.js-sel:checked')];

function aggiorna() {
    const barra = document.querySelector('[data-barra-selezione]');
    if (!barra) return;
    const n = selezionate().length;
    barra.hidden = !document.querySelector('.js-sel'); // nessuna riga eliminabile: niente barra
    barra.querySelector('[data-conteggio]').textContent = n ? `${n} selezionat${n === 1 ? 'a' : 'e'}` : 'Nessuna riga selezionata';
    barra.querySelector('[data-azione]').disabled = n === 0;
    barra.querySelector('[data-info]').hidden = n > 0;
}

// Dopo un'azione (o tornando indietro nella cronologia, quando il browser ripristina le caselle) non resta nulla di selezionato.
function deseleziona() {
    document.querySelectorAll('.js-sel, .js-sel-tutti').forEach((c) => { c.checked = false; });
    aggiorna();
}

document.addEventListener('DOMContentLoaded', deseleziona);
window.addEventListener('pageshow', deseleziona);

document.addEventListener('change', (e) => {
    if (e.target.matches('.js-sel-tutti')) {
        document.querySelectorAll('.js-sel').forEach((c) => { c.checked = e.target.checked; });
    }
    if (e.target.matches('.js-sel, .js-sel-tutti')) aggiorna();
});

// L'id del record è l'ultimo segmento dell'URL di eliminazione (/docenti/5).
const idDa = (url) => Number(url.replace(/\/+$/, '').split('/').pop());

// Cosa viene eliminato a cascata (o perde il collegamento): lo calcola il server. null = non disponibile.
async function conseguenze(barra, scelte) {
    if (!barra?.dataset.tabella) return null;
    const params = new URLSearchParams({ tabella: barra.dataset.tabella });
    scelte.forEach((c) => params.append('ids[]', idDa(c.value)));
    try {
        const r = await fetch(`${barra.dataset.urlConseguenze}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        return r.ok ? await r.json() : null;
    } catch {
        return null;
    }
}

const elenco = (voci) => voci.map((v) => `• ${v}`).join('\n');

// Un avviso che dice cosa va perso; se l'eliminazione porta via altri dati (cascata) serve una seconda conferma.
async function confermaEliminazione(scelte) {
    const nome = `${scelte.length} element${scelte.length === 1 ? 'o' : 'i'}`;
    const c = await conseguenze(document.querySelector('[data-barra-selezione]'), scelte);
    const cascata = c?.cascata ?? [];
    const collegamenti = c?.collegamenti ?? [];

    if (c && !cascata.length && !collegamenti.length) return confirm(`Eliminare ${nome}?`);

    let testo = `Eliminare ${nome}?`;
    if (!c) testo += '\n\nPotrebbero essere eliminati anche dati collegati (non è stato possibile elencarli).';
    if (cascata.length) testo += `\n\nVerranno eliminati ANCHE:\n${elenco(cascata)}`;
    if (collegamenti.length) testo += `\n\nPerderanno il collegamento (restano, ma senza questo dato):\n${elenco(collegamenti)}`;
    testo += '\n\nL\'operazione non si può annullare.';
    if (!confirm(testo)) return false;

    return !cascata.length && c ? true : confirm(`ULTIMA CONFERMA: eliminare definitivamente ${nome} e i dati collegati?\n\nNon sarà possibile recuperarli.`);
}

document.addEventListener('click', async (e) => {
    if (!e.target.matches('[data-azione="elimina"]')) return;
    const scelte = selezionate();
    if (!(await confermaEliminazione(scelte))) return;

    const token = document.querySelector('meta[name="csrf-token"]').content;
    let falliti = 0;
    for (const c of scelte) {
        const corpo = new FormData();
        corpo.append('_method', 'DELETE');
        corpo.append('_token', token);
        const r = await fetch(c.value, {
            method: 'POST', body: corpo, redirect: 'manual',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        if (r.type === 'opaqueredirect' || r.ok) c.closest('tr, [data-riga]').remove(); else falliti++;
    }
    deseleziona();
    if (!falliti) return location.reload();
    mostraToast(`${falliti} non eliminat${falliti === 1 ? 'o' : 'i'} (probabilmente in uso).`, 'avviso');
});
