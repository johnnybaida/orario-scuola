// Select con ricerca (vanilla: niente Select2/jQuery, vedi CLAUDE.md). Si attiva con <select data-ricerca>
// (valore "compatta" per le select piccole). La select originale resta nel DOM, nascosta, e riceve
// il valore scelto più un evento `change`: i listener esistenti continuano a funzionare.
const normalizza = (testo) => testo.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const CHEVRON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0 text-gray-400" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

let pannelloAperto = null;

function chiudiPannello() {
    pannelloAperto?.chiudi();
}

function migliora(select) {
    if (select.dataset.ricercaPronta) return;
    select.dataset.ricercaPronta = '1';

    const compatta = select.dataset.ricerca === 'compatta';
    const bottone = document.createElement('button');
    bottone.type = 'button';
    bottone.draggable = false;
    bottone.setAttribute('aria-haspopup', 'listbox');
    bottone.setAttribute('aria-expanded', 'false');
    bottone.className = `${select.className.replace(/\bjs-\S+/g, '')} flex items-center justify-between gap-2 border border-gray-300 rounded-lg bg-white text-left cursor-pointer ${compatta ? 'px-1.5 py-1' : 'px-3 py-2 text-sm'}`;
    select.hidden = true;
    select.after(bottone);

    const aggiornaEtichetta = () => {
        bottone.innerHTML = `<span class="truncate">${select.selectedOptions[0]?.textContent.trim() ?? ''}</span>${CHEVRON}`;
    };
    aggiornaEtichetta();

    bottone.addEventListener('click', () => apri(select, bottone, aggiornaEtichetta));
}

function apri(select, bottone, aggiornaEtichetta) {
    chiudiPannello();
    const opzioni = [...select.options].filter((o) => o.value !== '');
    const pannello = document.createElement('div');
    pannello.className = 'fixed z-50 bg-white border border-gray-200 rounded-lg shadow-lg';
    pannello.draggable = false;
    pannello.innerHTML = `<input type="search" aria-label="Cerca" placeholder="Cerca…" autocomplete="off"
            class="w-full border-0 border-b border-gray-200 rounded-none rounded-t-lg">
        <ul role="listbox" class="max-h-60 overflow-y-auto py-1"></ul>`;
    const campo = pannello.querySelector('input');
    const lista = pannello.querySelector('ul');
    let visibili = [];
    let evidenziata = 0;

    const disegna = () => {
        const parole = normalizza(campo.value).split(/\s+/).filter(Boolean);
        visibili = opzioni.filter((o) => parole.every((p) => normalizza(o.textContent).includes(p)));
        evidenziata = Math.min(evidenziata, Math.max(visibili.length - 1, 0));
        lista.replaceChildren(...visibili.map((o, i) => {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(o.value === select.value));
            li.textContent = o.textContent.trim();
            li.className = `px-3 py-1.5 text-sm cursor-pointer ${i === evidenziata ? 'bg-gray-100' : ''} ${o.value === select.value ? 'font-medium' : ''}`;
            li.addEventListener('mousedown', (e) => { e.preventDefault(); scegli(o); });
            return li;
        }));
        if (!visibili.length) lista.innerHTML = '<li class="px-3 py-1.5 text-sm text-gray-500">Nessun risultato</li>';
    };

    const chiudi = () => {
        pannello.remove();
        bottone.setAttribute('aria-expanded', 'false');
        document.removeEventListener('mousedown', fuori, true);
        window.removeEventListener('scroll', chiudi, true);
        pannelloAperto = null;
    };
    const fuori = (e) => { if (!pannello.contains(e.target) && !bottone.contains(e.target)) chiudi(); };
    const scegli = (opzione) => {
        select.value = opzione.value;
        aggiornaEtichetta();
        chiudi();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    campo.addEventListener('input', () => { evidenziata = 0; disegna(); });
    campo.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { chiudi(); bottone.focus(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (visibili[evidenziata]) scegli(visibili[evidenziata]); }
        else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            evidenziata = Math.max(0, Math.min(visibili.length - 1, evidenziata + (e.key === 'ArrowDown' ? 1 : -1)));
            disegna();
            lista.children[evidenziata]?.scrollIntoView({ block: 'nearest' });
        }
    });

    document.body.append(pannello);
    const r = bottone.getBoundingClientRect();
    pannello.style.width = `${Math.max(r.width, 240)}px`;
    pannello.style.left = `${Math.min(r.left, window.innerWidth - Math.max(r.width, 240) - 8)}px`;
    // Sotto il bottone; sopra se non c'è spazio.
    if (r.bottom + 300 > window.innerHeight && r.top > 300) pannello.style.bottom = `${window.innerHeight - r.top + 4}px`;
    else pannello.style.top = `${r.bottom + 4}px`;

    bottone.setAttribute('aria-expanded', 'true');
    document.addEventListener('mousedown', fuori, true);
    window.addEventListener('scroll', chiudi, true);
    pannelloAperto = { chiudi };
    disegna();
    campo.focus();
}

const inizializza = () => document.querySelectorAll('select[data-ricerca]').forEach(migliora);
document.addEventListener('DOMContentLoaded', inizializza);
document.addEventListener('modale:caricata', inizializza);
