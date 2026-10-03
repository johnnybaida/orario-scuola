{{-- Unico Salva/Annulla delle pagine di modifica, sempre in fondo alla viewport (a destra della sidebar su desktop).
     Nelle modali c'è il piè di pagina della modale. Il distanziatore evita che copra l'ultimo contenuto. --}}
@props(['annulla', 'etichetta' => 'Salva'])

@unless (request()->ajax())
    <div class="h-20" aria-hidden="true"></div>
    <div class="fixed bottom-0 inset-x-0 md:left-60 z-20 border-t border-gray-200 bg-white/95 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 py-3 flex justify-end gap-3">
            <a href="{{ $annulla }}" class="rounded border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer">Annulla</a>
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">{{ $etichetta }}</button>
        </div>
    </div>
@endunless
