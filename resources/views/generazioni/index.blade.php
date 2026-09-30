@extends('layouts.app')

@section('titolo', 'Generazioni orario')

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Generazioni orario</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('generazioni.create') }}" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Nuova generazione</a>
        @endcan
    </div>

    <x-guida>
        Ogni generazione lancia il solver (OR-Tools) in background per produrre un orario che rispetta i vincoli
        rigidi e minimizza le violazioni di quelli preferenziali. Il seed determina il risultato in modo
        riproducibile: stesso seed e stessi dati producono lo stesso orario. Serve un worker di coda attivo
        (<code>php artisan queue:work</code>).
    </x-guida>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Stato</th>
                    <th class="px-4 py-2">Progresso</th>
                    <th class="px-4 py-2">Seed</th>
                    <th class="px-4 py-2">Punteggio</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($generazioni as $g)
                    <tr>
                        <td class="px-4 py-2">{{ $g->id }}</td>
                        <td class="px-4 py-2">{{ $g->stato }}</td>
                        <td class="px-4 py-2">{{ $g->progresso }}%</td>
                        <td class="px-4 py-2">{{ $g->seed }}</td>
                        <td class="px-4 py-2">{{ $g->orario?->punteggio }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('generazioni.show', $g) }}" class="text-gray-600 hover:text-gray-900 underline">Dettaglio</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
