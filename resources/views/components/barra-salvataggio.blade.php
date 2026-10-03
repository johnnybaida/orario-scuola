{{-- Unico Salva/Annulla delle pagine di modifica, fisso in basso a destra. Nelle modali c'è il piè di pagina della modale. --}}
@props(['annulla'])

@unless (request()->ajax())
    <div class="sticky bottom-0 z-10 mt-6 -mx-4 px-4 py-3 flex justify-end gap-3 bg-white/95 border-t border-gray-200 backdrop-blur">
        <a href="{{ $annulla }}" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">Annulla</a>
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
    </div>
@endunless
