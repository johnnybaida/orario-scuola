@extends('layouts.app')

@section('titolo', 'Discipline')

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Discipline</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('discipline.create') }}" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Nuova disciplina</a>
        @endcan
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Codice</th>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Classe di concorso</th>
                    <th class="px-4 py-2">Tipo aula richiesto</th>
                    <th class="px-4 py-2">Sotto-disciplina di</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($discipline as $disciplina)
                    <tr>
                        <td class="px-4 py-2 font-mono">{{ $disciplina->codice }}</td>
                        <td class="px-4 py-2">{{ $disciplina->nome }}</td>
                        <td class="px-4 py-2">{{ $disciplina->classe_concorso }}</td>
                        <td class="px-4 py-2">{{ $disciplina->tipo_aula_richiesto }}</td>
                        <td class="px-4 py-2">{{ $disciplina->padre?->nome }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a href="{{ route('discipline.edit', $disciplina) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                                <form method="POST" action="{{ route('discipline.destroy', $disciplina) }}" class="inline" onsubmit="return confirm('Eliminare questa disciplina?');">
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
