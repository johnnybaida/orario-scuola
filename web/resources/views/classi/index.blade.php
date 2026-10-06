@extends('layouts.app')

@section('titolo', 'Classi')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Classi</h1>

    <x-guida>
        Anagrafica delle classi: anno, sezione, sede, quadro orario e "slot attivi" (le ore della scansione
        settimanale effettivamente usate, es. anche il pomeriggio per il tempo prolungato). Apri una classe per
        impostare gli slot attivi e vedere le cattedre assegnate.
    </x-guida>

    <x-barra-tabella>
        <x-slot:azioni>
            @can('gestisci-docenti-classi')
                <div class="flex gap-3">
                    <x-csv-azioni lista="classi" />
                    <a href="{{ route('classi.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova classe</a>
                </div>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-docenti-classi')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Classe</th>
                    <th class="px-4 py-2">Sede</th>
                    <th class="px-4 py-2">Quadro orario</th>
                    <th class="px-4 py-2">Tempo scuola</th>
                    <th class="px-4 py-2">Alunni</th>
                    <th class="px-4 py-2">Cattedre</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($classi as $classe)
                    <tr>
                        @can('gestisci-docenti-classi')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('classi.destroy', $classe) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $classe->nomeCompleto() }}</td>
                        <td class="px-4 py-2">{{ $classe->sede->nome }}</td>
                        <td class="px-4 py-2">{{ $classe->quadroOrario->nome }}</td>
                        <td class="px-4 py-2">{{ $classe->tempo_scuola }}</td>
                        <td class="px-4 py-2">{{ $classe->n_alunni }}</td>
                        <td class="px-4 py-2">{{ $classe->cattedre_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <a href="{{ route('classi.edit', $classe) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
