{{-- Collegamenti Esporta/Importa CSV di una lista (vedi App\Services\ListeCsv); l'import solo a chi può modificarla. --}}
@props(['lista'])
<a href="{{ route('csv.esporta', $lista) }}" class="text-sm underline text-gray-600">Esporta CSV</a>
@if (! (\App\Services\ListeCsv::LISTE[$lista]['solo_export'] ?? false))
@can(\App\Services\ListeCsv::LISTE[$lista]['permesso'])
    <a href="{{ route('csv.form', $lista) }}" class="text-sm underline text-gray-600">Importa CSV</a>
@endcan
@endif
