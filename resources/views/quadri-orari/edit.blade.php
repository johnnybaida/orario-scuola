@extends('layouts.app')

@section('titolo', 'Quadro orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">{{ $quadro->nome }}</h1>
    <p class="text-sm text-gray-500 mb-6">Totale: {{ $quadro->ore_totali }} ore settimanali</p>

    <x-guida>
        Aggiungi qui le discipline e le ore settimanali di questo quadro orario. Il totale deve coincidere con le
        ore assegnate nelle cattedre di ogni classe che lo usa.
    </x-guida>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">Disciplina</th>
                        <th class="px-4 py-2">Ore/sett.</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($quadro->righe as $riga)
                        <tr>
                            <td class="px-4 py-2">{{ $riga->disciplina->nome }}</td>
                            <td class="px-4 py-2">{{ $riga->ore_settimanali }}</td>
                            <td class="px-4 py-2 text-right">
                                @can('gestisci-anagrafica')
                                    <form method="POST" action="{{ route('quadri-orari.righe.destroy', $riga) }}" onsubmit="return confirm('Rimuovere questa riga?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 underline">Rimuovi</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @can('gestisci-anagrafica')
            <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
                <h2 class="font-medium">Aggiungi/aggiorna disciplina</h2>
                <form method="POST" action="{{ route('quadri-orari.righe.store', $quadro) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="disciplina_id" class="block text-sm font-medium text-gray-700">Disciplina</label>
                        <select name="disciplina_id" id="disciplina_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                            @foreach ($discipline as $disciplina)
                                <option value="{{ $disciplina->id }}">{{ $disciplina->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="ore_settimanali" class="block text-sm font-medium text-gray-700">Ore settimanali</label>
                        <input type="number" name="ore_settimanali" id="ore_settimanali" min="1" max="40" required
                               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva riga</button>
                </form>

                <hr class="border-gray-100">

                <form method="POST" action="{{ route('quadri-orari.update', $quadro) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="nome" class="block text-sm font-medium text-gray-700">Nome quadro</label>
                        <input type="text" name="nome" id="nome" value="{{ old('nome', $quadro->nome) }}" required
                               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <button type="submit" class="text-sm underline text-gray-600">Rinomina quadro</button>
                </form>
            </div>
        @endcan
    </div>
@endsection
