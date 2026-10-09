{{-- Collegamenti Esporta/Importa CSV di una lista (vedi App\Services\ListeCsv); l'import solo a chi può modificarla. Con `titolo` più liste stanno nella stessa barra. --}}
@props(['lista', 'titolo' => null])
<a href="{{ route('csv.esporta', $lista) }}" class="text-sm underline text-gray-600">Esporta {{ $titolo ? $titolo.' ' : '' }}CSV</a>
@can(\App\Services\ListeCsv::LISTE[$lista]['permesso'])
    <a href="{{ route('csv.form', $lista) }}" class="text-sm underline text-gray-600">Importa {{ $titolo ? $titolo.' ' : '' }}CSV</a>
@endcan
