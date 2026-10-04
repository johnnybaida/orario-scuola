// Scansione oraria: cambiando la fine di un'ora o la sua ricreazione, le ore successive scorrono dello stesso tempo,
// così l'inizio della successiva resta coerente con la ricreazione. Scorrono solo le ore "agganciate" (inizio = fine
// della precedente + ricreazione prima della modifica): una pausa lasciata a mano (es. il pranzo) non si tocca.
const form = document.querySelector('form[data-scansione]');

if (form) {
    const campo = (ora, nome) => form.querySelector(`[data-ora="${ora}"][data-campo="${nome}"]`);
    const minuti = (hhmm) => { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; };
    const testo = (min) => `${String(Math.floor(min / 60) % 24).padStart(2, '0')}:${String(min % 60).padStart(2, '0')}`;
    const ore = [...new Set([...form.querySelectorAll('[data-ora]')].map((e) => Number(e.dataset.ora)))].sort((a, b) => a - b);

    // Stato precedente di ogni campo, per sapere di quanto è cambiato.
    const prima = new Map();
    form.querySelectorAll('[data-ora]').forEach((e) => prima.set(e, e.value));

    form.addEventListener('change', (evento) => {
        const el = evento.target;
        if (!el.dataset?.ora || el.dataset.campo === 'inizio') {
            if (el.dataset?.ora) prima.set(el, el.value);
            return;
        }

        const n = Number(el.dataset.ora);
        const fine = campo(n, 'fine');
        const ric = campo(n, 'ricreazione');
        // Valori prima della modifica: il campo appena cambiato ha il vecchio valore in `prima`.
        const durataPrima = Number((el === ric ? prima.get(ric) : ric?.value) || 0);
        const finePrima = el === fine ? prima.get(fine) : fine.value;
        prima.set(el, el.value);

        if (!fine.value || !finePrima) return;
        const delta = (minuti(fine.value) + Number(ric?.value || 0)) - (minuti(finePrima) + durataPrima);

        // Valori di tutte le ore successive prima dello spostamento, per decidere quali sono agganciate.
        const vecchie = ore.slice(ore.indexOf(n) + 1).map((o) => ({
            inizio: campo(o, 'inizio'), fine: campo(o, 'fine'), ric: Number(campo(o, 'ricreazione')?.value || 0),
            i0: minuti(campo(o, 'inizio').value), f0: minuti(campo(o, 'fine').value),
        }));
        let attesa = minuti(finePrima) + durataPrima; // inizio che l'ora successiva avrebbe se contigua
        for (const ora of vecchie) {
            if (ora.i0 !== attesa) break;
            ora.inizio.value = testo(ora.i0 + delta);
            ora.fine.value = testo(ora.f0 + delta);
            prima.set(ora.inizio, ora.inizio.value);
            prima.set(ora.fine, ora.fine.value);
            attesa = ora.f0 + ora.ric;
        }
    });
}
