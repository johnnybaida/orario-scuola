@extends('layouts.app')

@section('titolo', 'Quadri orari')

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Quadri orari</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('quadri-orari.create') }}" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Nuovo quadro orario</a>
        @endcan
    </div>

    <x-guida>
        Un quadro orario è il monte ore settimanale per disciplina di un percorso (es. "Tempo normale 30h").
        Ogni classe ne usa uno; le cattedre di quella classe devono coprire esattamente queste ore, altrimenti
        la generazione dell'orario segnala un'incoerenza in fase di pre-validazione.
    </x-guida>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Ore totali</th>
                    <th class="px-4 py-2">Classi</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($quadri as $quadro)
                    <tr>
                        <td class="px-4 py-2">{{ $quadro->nome }}</td>
                        <td class="px-4 py-2">{{ $quadro->ore_totali }}</td>
                        <td class="px-4 py-2">{{ $quadro->classi_count }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            <a href="{{ route('quadri-orari.edit', $quadro) }}" class="text-gray-600 hover:text-gray-900 underline">Apri</a>
                            @can('gestisci-anagrafica')
                                <form method="POST" action="{{ route('quadri-orari.destroy', $quadro) }}" class="inline" onsubmit="return confirm('Eliminare questo quadro orario?');">
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
