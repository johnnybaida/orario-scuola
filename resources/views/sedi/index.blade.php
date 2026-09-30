@extends('layouts.app')

@section('titolo', 'Sedi')

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Sedi</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('sedi.create') }}" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Nuova sede</a>
        @endcan
    </div>

    <x-guida>
        Le sedi rappresentano i plessi dell'istituto (una sola scuola per installazione, ma può avere più plessi).
        Ogni aula appartiene a una sede. Se l'istituto ha un solo plesso basta una sede.
    </x-guida>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Indirizzo</th>
                    <th class="px-4 py-2">Aule</th>
                    <th class="px-4 py-2">Classi</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($sedi as $sede)
                    <tr>
                        <td class="px-4 py-2">{{ $sede->nome }}</td>
                        <td class="px-4 py-2">{{ $sede->indirizzo }}</td>
                        <td class="px-4 py-2">{{ $sede->aule_count }}</td>
                        <td class="px-4 py-2">{{ $sede->classi_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a href="{{ route('sedi.edit', $sede) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                                <form method="POST" action="{{ route('sedi.destroy', $sede) }}" class="inline" onsubmit="return confirm('Eliminare questa sede?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 underline">Elimina</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
