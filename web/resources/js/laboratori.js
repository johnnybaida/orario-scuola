// Form dei laboratori: colora in giallo le ore pomeridiane non libere per i docenti e l'aula scelti (e spiega il motivo).
function aggiorna(modulo) {
    const params = new URLSearchParams();
    modulo.querySelectorAll('[data-campo-docente]:checked').forEach((c) => params.append('docenti[]', c.value));
    const aula = modulo.querySelector('[data-campo-aula]')?.value;
    if (aula) params.set('aula_id', aula);
    if (modulo.dataset.escludi) params.set('escludi', modulo.dataset.escludi);

    fetch(`${modulo.dataset.url}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => (r.ok ? r.json() : {}))
        .then((motivi) => {
            modulo.querySelectorAll('[data-slot-etichetta]').forEach((el) => {
                const lista = motivi[el.dataset.slotEtichetta] || [];
                el.classList.toggle('bg-amber-100', lista.length > 0);
                el.title = lista.join('\n');
            });
        })
        .catch(() => {});
}

// Il form può comparire dopo il caricamento (modale): si ascolta sul documento.
document.addEventListener('change', (e) => {
    const modulo = e.target.closest?.('[data-laboratorio]');
    if (modulo && e.target.matches('[data-campo-docente], [data-campo-aula]')) aggiorna(modulo);
});
document.addEventListener('modale:caricata', () => document.querySelectorAll('[data-laboratorio]').forEach(aggiorna));
document.querySelectorAll('[data-laboratorio]').forEach(aggiorna);
