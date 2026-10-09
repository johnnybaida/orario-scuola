// Menu a tendina (<details data-menu-profilo> del profilo, <details data-menu> come Importa/Esporta): si chiude cliccando fuori o con Esc.
const APERTI = 'details[data-menu-profilo][open], details[data-menu][open]';

document.addEventListener('click', (e) => {
    document.querySelectorAll(APERTI).forEach((d) => {
        if (!d.contains(e.target)) d.open = false;
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll(APERTI).forEach((d) => {
        d.open = false;
        d.querySelector('summary')?.focus();
    });
});
