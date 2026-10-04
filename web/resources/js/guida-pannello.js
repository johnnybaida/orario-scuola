// Pannello di aiuto a destra (contenuto da docs/guida-utente.md via /guida). Si apre con il pulsante "Aiuto" o F1
// sulla sezione della pagina corrente (data-contesto); cerca nel testo di tutte le sezioni.
// Si chiude con ×, Esc o di nuovo F1. Con una modale aperta il resto della pagina è inerte: lì non si apre.
let sezioni = null;
let aperto = false;
let apritore = null;

const pannello = () => document.querySelector('#pannello-guida');
const campoRicerca = () => pannello().querySelector('[data-cerca-guida]');
const corpo = () => pannello().querySelector('[data-testo-guida]');

// Minuscolo e senza accenti, carattere per carattere: la lunghezza non cambia, così gli indici combaciano col testo originale.
const normalizza = (testo) => [...testo].map((c) => {
    const n = c.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    return n.length === 1 ? n : c.toLowerCase().slice(0, 1);
}).join('');
const parole = (query) => normalizza(query).split(/\s+/).filter(Boolean);
const escapeHtml = (t) => t.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

async function carica() {
    try {
        const risposta = await fetch(pannello().dataset.url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!risposta.ok) throw new Error(risposta.status);
        sezioni = (await risposta.json()).sezioni;
    } catch {
        corpo().textContent = 'Impossibile caricare la guida. Riprova più tardi.';
        return false;
    }

    const contenitore = document.createElement('div');
    sezioni.forEach((s) => {
        contenitore.innerHTML = s.html;
        s.testo = `${s.titolo}. ${contenitore.textContent.replace(/\s+/g, ' ')}`;
    });

    const menu = pannello().querySelector('[data-menu-guida]');
    sezioni.forEach((sezione) => {
        const voce = document.createElement('button');
        voce.type = 'button';
        voce.dataset.sezione = sezione.id;
        voce.textContent = sezione.titolo;
        voce.className = 'rounded-full border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer aria-[current=true]:bg-primary aria-[current=true]:text-white aria-[current=true]:border-primary';
        voce.addEventListener('click', () => { campoRicerca().value = ''; mostra(sezione.id); });
        menu.append(voce);
    });
    return true;
}

function evidenzia(radice, termini) {
    const nodi = [];
    for (const walker = document.createTreeWalker(radice, NodeFilter.SHOW_TEXT); walker.nextNode();) nodi.push(walker.currentNode);

    nodi.forEach((nodo) => {
        const originale = nodo.textContent;
        const norm = normalizza(originale);
        const intervalli = [];
        termini.forEach((t) => {
            for (let i = norm.indexOf(t); i !== -1; i = norm.indexOf(t, i + t.length)) intervalli.push([i, i + t.length]);
        });
        if (!intervalli.length) return;

        intervalli.sort((a, b) => a[0] - b[0]);
        const frammento = document.createDocumentFragment();
        let cursore = 0;
        intervalli.forEach(([da, a]) => {
            if (da < cursore) return;
            frammento.append(originale.slice(cursore, da));
            const mark = document.createElement('mark');
            mark.className = 'bg-yellow-200 rounded-sm';
            mark.textContent = originale.slice(da, a);
            frammento.append(mark);
            cursore = a;
        });
        frammento.append(originale.slice(cursore));
        nodo.replaceWith(frammento);
    });
}

function mostra(id, termini = []) {
    const sezione = sezioni.find((s) => s.id === id) ?? sezioni[0];
    corpo().innerHTML = `<h3 class="guida-titolo">${escapeHtml(sezione.titolo)}</h3>${sezione.html}`; // HTML generato dal server da Markdown (html_input: strip)
    if (termini.length) {
        evidenzia(corpo(), termini);
        corpo().querySelector('mark')?.scrollIntoView({ block: 'center' });
    } else {
        corpo().scrollTo(0, 0);
    }
    pannello().querySelectorAll('[data-sezione]').forEach((v) => v.setAttribute('aria-current', String(v.dataset.sezione === sezione.id)));
}

function cerca() {
    const termini = parole(campoRicerca().value);
    if (!termini.length) return mostra(pannello().querySelector('[data-sezione][aria-current="true"]')?.dataset.sezione ?? sezioni[0].id);

    const risultati = sezioni.filter((s) => termini.every((t) => normalizza(s.testo).includes(t)));
    pannello().querySelectorAll('[data-sezione]').forEach((v) => v.setAttribute('aria-current', 'false'));
    if (!risultati.length) {
        corpo().innerHTML = `<p>Nessun risultato per «${escapeHtml(campoRicerca().value.trim())}».</p>`;
        return;
    }

    corpo().replaceChildren(...risultati.map((s) => {
        const norm = normalizza(s.testo);
        const posizione = Math.max(0, norm.indexOf(termini[0]) - 40);
        const brano = `${posizione > 0 ? '…' : ''}${s.testo.slice(posizione, posizione + 140)}…`;
        const voce = document.createElement('button');
        voce.type = 'button';
        voce.className = 'mb-2 block w-full rounded border border-gray-200 px-3 py-2 text-left hover:bg-gray-50 transition-colors cursor-pointer';
        voce.innerHTML = `<span class="font-medium text-foreground">${escapeHtml(s.titolo)}</span><span class="mt-0.5 block text-xs text-gray-500">${escapeHtml(brano)}</span>`;
        evidenzia(voce.lastChild, termini);
        voce.addEventListener('click', () => mostra(s.id, termini));
        return voce;
    }));
}

async function imposta(visibile) {
    if (visibile && document.querySelector('dialog[open]')) return;
    aperto = visibile;
    pannello().hidden = !visibile;
    document.querySelectorAll('[data-apri-guida]').forEach((b) => b.setAttribute('aria-expanded', String(visibile)));

    if (!visibile) {
        apritore?.focus();
        return;
    }
    apritore = document.activeElement;
    if (!sezioni && !(await carica())) return;
    // Ogni apertura parte dalla sezione della pagina corrente, con la ricerca azzerata.
    campoRicerca().value = '';
    mostra(pannello().dataset.contesto || sezioni[0].id);
    campoRicerca().focus();
}

document.addEventListener('click', (e) => {
    if (!pannello()) return;
    if (e.target.closest('[data-apri-guida]')) imposta(!aperto);
    if (e.target.closest('[data-chiudi-guida]')) imposta(false);
});

document.addEventListener('input', (e) => {
    if (e.target.matches('[data-cerca-guida]') && sezioni) cerca();
});

document.addEventListener('keydown', (e) => {
    if (!pannello()) return;
    if (e.key === 'F1') {
        e.preventDefault(); // niente aiuto del browser
        imposta(!aperto);
    } else if (e.key === 'Escape' && aperto) {
        imposta(false);
    }
});
