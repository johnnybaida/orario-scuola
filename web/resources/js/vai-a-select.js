// Navigazione tramite <select data-base="/percorso"> (usato in orari/index).
document.addEventListener('change', (evento) => {
    const select = evento.target;
    if (!select.matches('.js-vai-classe') || !select.value) return;

    window.location.href = `${select.dataset.base}/${select.value}`;
});

// Select che invia il proprio form quando cambia (es. la sede in cui si lavora).
document.addEventListener('change', (evento) => {
    if (evento.target.matches?.('select[data-invia-al-cambio]')) evento.target.form.requestSubmit();
});
