@extends('layouts.app')

@section('titolo', 'Docenti')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Docenti</h1>

    <x-guida>
        Anagrafica dei docenti: tipo di posto, regime orario, ore dovute (18h = cattedra intera), classi di
        concorso e indisponibilità (slot in cui non possono avere lezione). Apri un docente per vedere le sue
        cattedre e impostare le indisponibilità. Puoi importare più docenti insieme con un file CSV.
    </x-guida>

    <x-barra-tabella>
        <form method="GET" class="flex items-center gap-2">
            <input type="search" name="cerca" value="{{ $cerca }}" placeholder="Cerca per nome/cognome" aria-label="Cerca docente">
            <button type="submit" class="text-sm underline text-gray-600 cursor-pointer">Cerca</button>
        </form>
        <x-slot:azioni>
        @can('gestisci-docenti-classi')
            <x-csv-azioni lista="docenti" />
            <a href="{{ route('docenti.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuovo docente</a>
        @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione tabella="docenti" />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-docenti-classi')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Cognome Nome</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Tipo posto</th>
                    <th class="px-4 py-2">Regime</th>
                    <th class="px-4 py-2">Ore dovute</th>
                    <th class="px-4 py-2">Cattedre</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($docenti as $docente)
                    <tr>
                        @can('gestisci-docenti-classi')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('docenti.destroy', $docente) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $docente->nomeCompleto() }}
                            @if ($sospensione = $docente->sospensioneAttiva())
                                <span class="ml-2 inline-block rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800" title="{{ $sospensione->etichettaMotivo() }} {{ $sospensione->periodo() }}">Sospeso</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $docente->email }}</td>
                        <td class="px-4 py-2">{{ $docente->tipo_posto }}</td>
                        <td class="px-4 py-2">{{ $docente->regime }}</td>
                        <td class="px-4 py-2">{{ $docente->ore_dovute }}</td>
                        <td class="px-4 py-2">{{ $docente->cattedre_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <a href="{{ route('docenti.edit', $docente) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $docenti->links() }}</div>
@endsection
