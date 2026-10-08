// Menu del profilo (<details data-menu-profilo>): si chiude cliccando fuori o con Esc.
document.addEventListener('click', (e) => {
    document.querySelectorAll('details[data-menu-profilo][open]').forEach((d) => {
        if (!d.contains(e.target)) d.open = false;
    });
});

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details[data-menu-profilo][open]').forEach((d) => {
        d.open = false;
        d.querySelector('summary')?.focus();
    });
});
