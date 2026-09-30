// Navigazione tramite <select data-base="/percorso"> (usato in orari/index).
document.addEventListener('change', (evento) => {
    const select = evento.target;
    if (!select.matches('.js-vai-classe') || !select.value) return;

    window.location.href = `${select.dataset.base}/${select.value}`;
});
