// Parti comuni agli editor dell'orario (griglia della classe e tabellone per aula): chiamate al server,
// modalità «conflitti provvisori» e colorazione delle celle di destinazione durante il trascinamento.
const CHIAVE_PROVVISORIO = 'orario.conflitti-provvisori';

export const leggiProvvisorio = () => { try { return localStorage.getItem(CHIAVE_PROVVISORIO) === '1'; } catch { return false; } };
export const salvaProvvisorio = (valore) => { try { localStorage.setItem(CHIAVE_PROVVISORIO, valore ? '1' : '0'); } catch { /* senza storage vale solo per questa pagina */ } };

export async function chiamaApi(url, method, corpo) {
    const risposta = await fetch(url, {
        method,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: corpo ? JSON.stringify(corpo) : undefined,
    });
    return { ok: risposta.ok, dati: await risposta.json() };
}

// Verde = possibile, ambra = possibile solo con i conflitti provvisori, rosso = non ammesso; il motivo è nel tooltip.
const STILI = {
    ok: ['ring-2', 'ring-inset', 'ring-green-500', 'bg-green-50'],
    conflitto: ['ring-2', 'ring-inset', 'ring-amber-500', 'bg-amber-50'],
    vietato: ['ring-2', 'ring-inset', 'ring-red-300', 'bg-red-50', 'opacity-70'],
};
const TUTTI_GLI_STILI = [...new Set(Object.values(STILI).flat())];

/** `chiave(cella)` dice quale voce di `esiti` riguarda la cella. */
export function coloraCelle(celle, esiti, provvisorio, chiave) {
    celle.forEach((cella) => {
        const esito = esiti[chiave(cella)];
        if (!esito) return;
        // Con i conflitti provvisori spenti un «conflitto» equivale a un divieto.
        const stato = esito.stato === 'conflitto' && !provvisorio ? 'vietato' : esito.stato;
        cella.classList.add(...STILI[stato]);
        if (esito.motivi.length) cella.title = esito.motivi.join('\n');
    });
}

export function pulisciCelle(celle) {
    celle.forEach((cella) => {
        cella.classList.remove(...TUTTI_GLI_STILI);
        cella.removeAttribute('title');
    });
}
