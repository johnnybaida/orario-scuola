@extends('layouts.app')

@section('titolo', 'Cattedre')

@section('contenuto')
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Cattedre</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('cattedre.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova cattedra</a>
        @endcan
    </div>

    <x-guida>
        Una cattedra assegna un docente a una disciplina per una classe, con un numero di ore settimanali. La
        somma delle cattedre di una classe deve coincidere con il suo quadro orario; la somma delle cattedre di
        un docente non dovrebbe superare le sue ore dovute.
    </x-guida>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div>
            <label class="block text-xs text-gray-500">Classe</label>
            <select name="classe_id" class="rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                <option value="">Tutte</option>
                @foreach ($classi as $classe)
                    <option value="{{ $classe->id }}" @selected($filtroClasse == $classe->id)>{{ $classe->nomeCompleto() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500">Docente</label>
            <select name="docente_id" class="rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                <option value="">Tutti</option>
                @foreach ($docenti as $docente)
                    <option value="{{ $docente->id }}" @selected($filtroDocente == $docente->id)>{{ $docente->nomeCompleto() }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Classe</th>
                    <th class="px-4 py-2">Disciplina</th>
                    <th class="px-4 py-2">Docente</th>
                    <th class="px-4 py-2">Ore</th>
                    <th class="px-4 py-2">Compresenza</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($cattedre as $cattedra)
                    <tr>
                        <td class="px-4 py-2">{{ $cattedra->classe->nomeCompleto() }}</td>
                        <td class="px-4 py-2">{{ $cattedra->disciplina->nome }}</td>
                        <td class="px-4 py-2">{{ $cattedra->docente->nomeCompleto() }}</td>
                        <td class="px-4 py-2">{{ $cattedra->ore }}</td>
                        <td class="px-4 py-2">{{ $cattedra->compresenza ? 'Sì' : 'No' }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a href="{{ route('cattedre.edit', $cattedra) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                                <form method="POST" action="{{ route('cattedre.destroy', $cattedra) }}" class="inline" onsubmit="return confirm('Eliminare questa cattedra?');">
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

    <div class="mt-4">{{ $cattedre->links() }}</div>
@endsection
