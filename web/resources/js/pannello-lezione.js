// Pannello «modifica quest'ora» (✎ su ogni riquadro delle viste modificabili): cattedra (docente), aula, CLIL e fino a tre docenti di sostegno.
// Una sola finestra per tutte le viste (griglia della classe, tabellone, vista docente, vista aula). Le chiamate sono le stesse del resto
// dell'editor: gli esiti restano nel registro delle modifiche e la pagina si ricarica a ogni salvataggio.
import { chiamaApi, leggiProvvisorio } from './destinazioni.js';

const el = (tag, classi = '', ...figli) => {
    const nodo = document.createElement(tag);
    if (classi) nodo.className = classi;
    figli.forEach((f) => nodo.append(f));
    return nodo;
};
const CAMPO = 'mt-1 block w-full rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary';
const etichetta = (testo, controllo, extra) => el('label', 'block text-sm font-medium text-gray-700 mt-3', testo, controllo, ...(extra ? [extra] : []));
const opzione = (valore, testo, selezionata = false, disabilitata = false) => {
    const o = new Option(testo, valore, false, selezionata);
    o.disabled = disabilitata;
    return o;
};

async function apri(base, lezioneId) {
    const { ok, dati } = await chiamaApi(`${base}/${lezioneId}/dettaglio`, 'GET').catch(() => ({ ok: false }));
    if (!ok) return;
    const provvisorio = () => Boolean(document.querySelector('#conflitti-provvisori')?.checked ?? leggiProvvisorio());

    const finestra = el('dialog', 'm-auto rounded-lg p-0 backdrop:bg-black/40 w-[30rem] max-w-[95vw]');
    const form = el('form', 'p-5');
    form.method = 'dialog';
    form.append(el('h2', 'font-semibold', 'Modifica quest\'ora'), el('p', 'text-xs text-gray-500 mt-1', dati.titolo));

    // Docente = cattedra della stessa classe e disciplina.
    const selCattedra = el('select', CAMPO);
    dati.cattedre.forEach((c) => selCattedra.append(opzione(c.id, c.nome, c.id === dati.cattedra_id)));
    selCattedra.disabled = dati.bloccata || dati.cattedre.length < 2;
    form.append(etichetta('Docente', selCattedra, el('span', 'block text-xs font-normal text-gray-500',
        dati.bloccata ? 'La lezione è bloccata: sbloccala per cambiare il docente.' : (dati.cattedre.length < 2 ? 'Nessun\'altra cattedra per questa classe e disciplina.' : 'Le cattedre della stessa classe e disciplina.'))));

    // Sostituto: un altro docente fa questa ora al posto del titolare (o del docente CLIL), senza toccare la cattedra.
    const costruisciSostituto = (titolo, info) => {
        if (!info) return null;
        const s = el('select', CAMPO);
        s.append(opzione('', `— nessuno: resta ${info.originale} —`, !info.attuale));
        info.candidati.forEach((c) => {
            const occupato = !c.libero && c.id !== info.attuale;
            s.append(opzione(c.id, occupato ? `${c.nome} — ${c.motivi.join(', ')}` : c.nome, c.id === info.attuale, occupato && !provvisorio()));
        });
        form.append(etichetta(titolo, s));
        return s;
    };
    const selSostTitolare = costruisciSostituto(`Sostituto di ${dati.sostituzione.titolare.originale}`, dati.sostituzione.titolare);
    const selSostClil = costruisciSostituto(`Sostituto del docente CLIL ${dati.sostituzione.clil?.originale ?? ''}`, dati.sostituzione.clil);

    let selAula = null;
    if (dati.aule.length) {
        selAula = el('select', CAMPO);
        dati.aule.forEach((a) => selAula.append(opzione(a.id, a.nome, a.id === dati.aula_id)));
        form.append(etichetta('Aula', selAula));
    }

    let casellaClil = null;
    if (dati.clil) {
        casellaClil = el('input');
        casellaClil.type = 'checkbox';
        casellaClil.checked = dati.clil.attivo;
        const riga = el('label', 'mt-3 flex items-center gap-2 text-sm', casellaClil, `Compresenza CLIL con ${dati.clil.docente}`);
        form.append(riga);
        if (!dati.clil.libero) form.append(el('p', 'text-xs text-amber-700', `${dati.clil.docente} ${dati.clil.motivi.join(' e ')}: con i conflitti provvisori attivi si può comunque inserire.`));
    }

    const selSostegno = [0, 1, 2].map((i) => {
        const s = el('select', CAMPO);
        s.append(opzione('', '— nessuno —'));
        dati.candidati_sostegno.forEach((c) => {
            const occupato = !c.libero && !dati.sostegno.includes(c.id);
            s.append(opzione(c.id, occupato ? `${c.nome} — ${c.motivi.join(', ')}` : c.nome, dati.sostegno[i] === c.id, occupato && !provvisorio()));
        });
        return s;
    });
    form.append(el('p', 'mt-4 text-sm font-medium text-gray-700', 'Docenti di sostegno'));
    selSostegno.forEach((s, i) => form.append(etichetta(`Sostegno ${i + 1}`, s)));
    if (!dati.candidati_sostegno.length) form.append(el('p', 'text-xs text-gray-500', 'Nessun docente con tipo posto «Sostegno» censito.'));

    const errore = el('p', 'mt-3 text-sm text-red-700');
    const annulla = el('button', 'rounded border border-gray-300 px-4 py-2 text-sm cursor-pointer', 'Annulla');
    annulla.type = 'button';
    const salva = el('button', 'rounded bg-primary px-4 py-2 text-sm text-white cursor-pointer', 'Salva');
    salva.type = 'submit';
    form.append(errore, el('div', 'mt-5 flex justify-end gap-2', annulla, salva));
    finestra.append(form);
    document.body.append(finestra);
    finestra.showModal();

    annulla.addEventListener('click', () => finestra.close());
    finestra.addEventListener('close', () => finestra.remove());

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const scelti = selSostegno.map((s) => s.value).filter(Boolean);
        if (new Set(scelti).size !== scelti.length) { errore.textContent = 'Lo stesso docente di sostegno è scelto due volte.'; return; }
        salva.disabled = true;

        const corpo = { provvisorio: provvisorio() };
        const url = (parte) => `${base}/${lezioneId}/${parte}`;
        const cattedraCambiata = Number(selCattedra.value) !== dati.cattedra_id;
        const passi = [];
        if (cattedraCambiata) passi.push(() => chiamaApi(url('cattedra'), 'PATCH', { ...corpo, cattedra_id: selCattedra.value }));
        if (selAula && Number(selAula.value) !== dati.aula_id && !cattedraCambiata) passi.push(() => chiamaApi(url('aula'), 'PATCH', { ...corpo, aula_id: selAula.value }));
        // cambiare cattedra azzera il CLIL (appartiene alla cattedra di prima): in quel caso si lascia com'è.
        // il sostituto dipende dalla cattedra: cambiando cattedra viene azzerato, quindi in quel caso non si tocca
        const sost = (select, info, ruolo) => select && !cattedraCambiata && Number(select.value || 0) !== Number(info.attuale || 0)
            && passi.push(() => chiamaApi(url('sostituto'), 'PATCH', { ...corpo, ruolo, docente_id: select.value || null }));
        sost(selSostTitolare, dati.sostituzione.titolare, 'titolare');
        sost(selSostClil, dati.sostituzione.clil, 'clil');
        if (casellaClil && casellaClil.checked !== dati.clil.attivo && !cattedraCambiata) passi.push(() => chiamaApi(url('clil'), 'PATCH', { ...corpo, attivo: casellaClil.checked }));
        if (JSON.stringify(scelti.map(Number).sort()) !== JSON.stringify([...dati.sostegno].sort())) passi.push(() => chiamaApi(url('sostegno'), 'PUT', { ...corpo, docenti: scelti.map(Number) }));

        for (const passo of passi) {
            const risposta = await passo().catch(() => null);
            if (!risposta?.ok) break; // l'errore è nel registro delle modifiche, in cima alla pagina
        }
        window.location.reload();
    });
}

document.addEventListener('click', (evento) => {
    const pulsante = evento.target.closest('.js-modifica-lezione');
    if (!pulsante) return;
    const carta = pulsante.closest('[data-lezione-id]');
    const base = pulsante.closest('[data-url-lezioni]')?.dataset.urlLezioni;
    if (carta && base) apri(base, carta.dataset.lezioneId);
});
