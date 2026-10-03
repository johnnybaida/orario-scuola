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

document.addEventListener('DOMContentLoaded', aggiorna);

document.addEventListener('change', (e) => {
    if (e.target.matches('.js-sel-tutti')) {
        document.querySelectorAll('.js-sel').forEach((c) => { c.checked = e.target.checked; });
    }
    if (e.target.matches('.js-sel, .js-sel-tutti')) aggiorna();
});

document.addEventListener('click', async (e) => {
    if (!e.target.matches('[data-azione="elimina"]')) return;
    const scelte = selezionate();
    if (!confirm(`Eliminare ${scelte.length} element${scelte.length === 1 ? 'o' : 'i'}?`)) return;

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
        if (r.type === 'opaqueredirect' || r.ok) c.closest('tr').remove(); else falliti++;
    }
    if (!falliti) return location.reload();
    document.querySelector('[data-esito]').textContent = `${falliti} non eliminat${falliti === 1 ? 'o' : 'i'} (probabilmente in uso).`;
    aggiorna();
});
