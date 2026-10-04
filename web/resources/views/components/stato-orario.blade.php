{{-- Etichetta colorata dello stato di un orario (bozza, in revisione, approvato, pubblicato, archiviato). --}}
@props(['orario'])

@php($colori = [
    'bozza' => 'bg-gray-100 text-gray-700',
    'in_revisione' => 'bg-amber-100 text-amber-800',
    'approvato' => 'bg-blue-100 text-blue-800',
    'pubblicato' => 'bg-green-100 text-green-800',
    'archiviato' => 'bg-gray-200 text-gray-600',
])
<span {{ $attributes->merge(['class' => 'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium '.($colori[$orario->stato] ?? 'bg-gray-100 text-gray-700')]) }}>{{ $orario->etichettaStato() }}</span>
