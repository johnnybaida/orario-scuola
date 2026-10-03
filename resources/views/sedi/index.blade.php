@extends('layouts.app')

@section('titolo', 'Sedi')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Sedi</h1>

    <x-guida>
        Le sedi rappresentano i plessi dell'istituto (una sola scuola per installazione, ma può avere più plessi).
        Ogni aula appartiene a una sede. Se l'istituto ha un solo plesso basta una sede.
    </x-guida>

    <x-barra-tabella>
        <x-slot:azioni>
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('sedi.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova sede</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-anagrafica')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
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
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('sedi.destroy', $sede) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $sede->nome }}</td>
                        <td class="px-4 py-2">{{ $sede->indirizzo }}</td>
                        <td class="px-4 py-2">{{ $sede->aule_count }}</td>
                        <td class="px-4 py-2">{{ $sede->classi_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a data-modale href="{{ route('sedi.edit', $sede) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
