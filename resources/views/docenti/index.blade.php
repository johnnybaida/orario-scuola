@extends('layouts.app')

@section('titolo', 'Docenti')

@section('contenuto')
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Docenti</h1>
        <div class="flex items-center gap-3">
            <form method="GET" class="flex items-center gap-2">
                <input type="text" name="cerca" value="{{ $cerca }}" placeholder="Cerca per nome/cognome"
                       class="rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary">
                <button type="submit" class="text-sm underline text-gray-600">Cerca</button>
            </form>
            @can('gestisci-docenti-classi')
                <a href="{{ route('docenti.import.form') }}" class="text-sm underline text-gray-600">Importa CSV</a>
                <a href="{{ route('docenti.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuovo docente</a>
            @endcan
        </div>
    </div>

    <x-guida>
        Anagrafica dei docenti: tipo di posto, regime orario, ore dovute (18h = cattedra intera), classi di
        concorso e indisponibilità (slot in cui non possono avere lezione). Apri un docente per vedere le sue
        cattedre e impostare le indisponibilità. Puoi importare più docenti insieme con un file CSV.
    </x-guida>

    @if (session('errori_import'))
        <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach (session('errori_import') as $errore)
                    <li>{{ $errore }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
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
                        <td class="px-4 py-2">{{ $docente->nomeCompleto() }}</td>
                        <td class="px-4 py-2">{{ $docente->email }}</td>
                        <td class="px-4 py-2">{{ $docente->tipo_posto }}</td>
                        <td class="px-4 py-2">{{ $docente->regime }}</td>
                        <td class="px-4 py-2">{{ $docente->ore_dovute }}</td>
                        <td class="px-4 py-2">{{ $docente->cattedre_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <a href="{{ route('docenti.edit', $docente) }}" class="text-gray-600 hover:text-gray-900 underline">Apri</a>
                            @can('gestisci-docenti-classi')
                                <form method="POST" action="{{ route('docenti.destroy', $docente) }}" class="inline" onsubmit="return confirm('Eliminare questo docente?');">
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

    <div class="mt-4">{{ $docenti->links() }}</div>
@endsection
