{{-- Barra degli strumenti per le viste modificabili: conflitti provvisori e Annulla/Ripeti su più livelli (scorciatoie
     Ctrl/Cmd+Z e Ctrl/Cmd+Maiusc+Z, vedi editor-griglia.js). Richiede $orario, $puoAnnullare, $puoRipetere. --}}
    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
        <input type="checkbox" id="conflitti-provvisori" class="rounded border-gray-300"> Conflitti provvisori
        <x-info testo="Spento (consigliato): l'editor rifiuta le modifiche che creano un conflitto con un docente o con un'aula. Acceso: le accetta e il conflitto resta segnalato in rosso nel Controllo finché non lo risolvi, per esempio spostando le lezioni dell'altra classe. Utile per scambi che coinvolgono più classi." />
    </label>
    {{-- Annulla/Ripeti su più livelli; scorciatoie Ctrl/Cmd+Z e Ctrl/Cmd+Maiusc+Z (vedi editor-griglia.js). --}}
    @php($pulsante = 'inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer disabled:text-gray-400 disabled:bg-gray-50 disabled:cursor-not-allowed')
    <form id="form-annulla" method="POST" action="{{ route('orari.annulla-ultima', $orario) }}">
        @csrf
        <button type="submit" @disabled(! $puoAnnullare) title="Annulla l'ultima modifica (Ctrl/Cmd+Z)" class="{{ $pulsante }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5 5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
            Annulla
        </button>
    </form>
    <form id="form-ripeti" method="POST" action="{{ route('orari.ripeti', $orario) }}">
        @csrf
        <button type="submit" @disabled(! $puoRipetere) title="Ripete l'ultima modifica annullata (Ctrl/Cmd+Maiusc+Z)" class="{{ $pulsante }}">
            Ripeti
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5 5.5 5.5 0 0 0 9.5 20H13"/></svg>
        </button>
    </form>
