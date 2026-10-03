{{-- Barra azioni per le righe selezionate (checkbox .js-sel); gestita da resources/js/selezione-multipla.js.
     Senza righe selezionabili (es. permessi di sola lettura) il JS la nasconde. --}}
<div data-barra-selezione class="mb-3 flex items-center gap-3 rounded-lg bg-gray-100 border border-gray-200 px-4 py-2 text-sm">
    <span data-conteggio class="font-medium">Nessuna riga selezionata</span>
    <button type="button" data-azione="elimina" disabled
            class="text-destructive hover:underline cursor-pointer disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">Elimina selezionati</button>
    <x-info testo="Seleziona una o più righe con le caselle a sinistra per poterle eliminare." />
    <span data-esito class="text-amber-700"></span>
</div>
