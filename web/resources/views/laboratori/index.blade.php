@extends('layouts.app')

@section('titolo', 'Laboratori')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Laboratori</h1>

    <x-guida>
        I laboratori pomeridiani (per esempio Latino) non fanno parte del monte ore e non sono generati: li assegni tu a mano a
        docenti, aula e ore del pomeriggio. L'orario ne tiene conto: il generatore non mette lezioni a un docente o in un'aula
        occupati da un laboratorio, e il Controllo segnala i conflitti. Quando scegli le ore, quelle già occupate sono in giallo.
    </x-guida>

    @if ($conflitti)
        <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            <p class="font-medium mb-1">Conflitti con l'orario di riferimento</p>
            <ul class="list-disc list-inside space-y-1">
                @foreach ($conflitti as $testo)
                    <li>{{ $testo }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-barra-tabella>
        <x-slot:azioni>
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('laboratori.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuovo laboratorio</a>
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
                    <th class="px-4 py-2">Docenti</th>
                    <th class="px-4 py-2">Aula</th>
                    <th class="px-4 py-2">Quando</th>
                    <th class="px-4 py-2">Classi</th>
                    <th class="px-4 py-2">Partecipanti</th>
                    <th class="px-4 py-2">Attivo</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($laboratori as $lab)
                    <tr>
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('laboratori.destroy', $lab) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $lab->nome }}</td>
                        <td class="px-4 py-2">{{ $lab->docenti->map->nomeCompleto()->implode(', ') }}</td>
                        <td class="px-4 py-2">{{ $lab->aula?->nome ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $lab->quando() }}</td>
                        <td class="px-4 py-2">{{ $lab->classi->map->nomeCompleto()->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $lab->n_partecipanti ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $lab->attivo ? 'Sì' : 'No' }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a data-modale href="{{ route('laboratori.edit', $lab) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-500">Nessun laboratorio.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
