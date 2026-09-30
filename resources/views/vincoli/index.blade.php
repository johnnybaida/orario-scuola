@extends('layouts.app')

@section('titolo', 'Vincoli')

@section('contenuto')
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Vincoli</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('vincoli.create') }}" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Nuovo vincolo</a>
        @endcan
    </div>

    <form method="GET" class="mb-4">
        <select name="tipo" class="rounded border-gray-300 shadow-sm text-sm focus:border-gray-500 focus:ring-gray-500" onchange="this.form.submit()">
            <option value="">Tutti i tipi</option>
            @foreach ($etichette as $tipo => $etichetta)
                <option value="{{ $tipo }}" @selected($filtroTipo === $tipo)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Ambito</th>
                    <th class="px-4 py-2">Severità</th>
                    <th class="px-4 py-2">Peso</th>
                    <th class="px-4 py-2">Attivo</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($vincoli as $vincolo)
                    <tr>
                        <td class="px-4 py-2">{{ $etichette[$vincolo->tipo] ?? $vincolo->tipo }}</td>
                        <td class="px-4 py-2">{{ $vincolo->ambito_livello }}</td>
                        <td class="px-4 py-2">{{ $vincolo->severita }}</td>
                        <td class="px-4 py-2">{{ $vincolo->peso }}</td>
                        <td class="px-4 py-2">{{ $vincolo->attivo ? 'Sì' : 'No' }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a href="{{ route('vincoli.edit', $vincolo) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                                <form method="POST" action="{{ route('vincoli.destroy', $vincolo) }}" class="inline" onsubmit="return confirm('Eliminare questo vincolo?');">
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
