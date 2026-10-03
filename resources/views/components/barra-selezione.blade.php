{{-- Barra azioni per le righe selezionate (checkbox .js-sel); gestita da resources/js/selezione-multipla.js --}}
<div data-barra-selezione hidden class="mb-3 flex items-center gap-3 rounded-lg bg-gray-100 border border-gray-200 px-4 py-2 text-sm">
    <span data-conteggio class="font-medium"></span>
    <button type="button" data-azione="elimina" class="text-destructive hover:underline cursor-pointer">Elimina selezionati</button>
    <span data-esito class="text-amber-700"></span>
</div>
