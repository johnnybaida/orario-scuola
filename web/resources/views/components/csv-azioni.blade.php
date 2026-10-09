{{-- Menu a tendina «Importa / Esporta» di una o più liste CSV (vedi App\Services\ListeCsv), da mettere accanto al pulsante «Nuovo».
     `lista` = una lista; `liste` = [lista => titolo] per più liste nello stesso menu. L'import solo a chi può modificare la lista. --}}
@props(['lista' => null, 'liste' => null, 'titolo' => null])
@php($elenco = $liste ?? [$lista => $titolo])
<details data-menu class="relative inline-block text-left">
    <summary class="list-none inline-flex items-center gap-1.5 rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer select-none [&::-webkit-details-marker]:hidden">
        Importa / Esporta
        <svg class="size-3.5 text-gray-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
    </summary>
    <div class="absolute right-0 mt-1 min-w-52 rounded-lg border border-gray-200 bg-white shadow-lg z-30 py-1 text-sm whitespace-nowrap">
        @foreach ($elenco as $l => $t)
            @if ($liste)
                <p class="px-4 pt-2 pb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ \App\Services\ListeCsv::LISTE[$l]['titolo'] }}</p>
            @endif
            <a href="{{ route('csv.esporta', $l) }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">Esporta CSV</a>
            @can(\App\Services\ListeCsv::LISTE[$l]['permesso'])
                <a href="{{ route('csv.form', $l) }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">Importa CSV</a>
            @endcan
        @endforeach
    </div>
</details>
