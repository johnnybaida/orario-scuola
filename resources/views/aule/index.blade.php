@extends('layouts.app')

@section('titolo', 'Aule')

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Aule</h1>
        @can('gestisci-anagrafica')
            <a href="{{ route('aule.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova aula</a>
        @endcan
    </div>

    <x-guida>
        Ogni aula ha un tipo (classe, laboratorio, palestra, ...) e una capienza: quante lezioni con lo stesso
        tipo di aula richiesto possono coesistere nello stesso slot in tutto l'istituto. Per la didattica DADA
        (un'aula dedicata a una disciplina) crea un'aula con un tipo a piacere (es. "dada_italiano") e collegalo
        alla disciplina in "Tipo aula richiesto" nella sua scheda.
    </x-guida>

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Sede</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Capienza</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($aule as $aula)
                    <tr>
                        <td class="px-4 py-2">{{ $aula->nome }}</td>
                        <td class="px-4 py-2">{{ $aula->sede->nome }}</td>
                        <td class="px-4 py-2">{{ $aula->tipo }}</td>
                        <td class="px-4 py-2">{{ $aula->capienza }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a href="{{ route('aule.edit', $aula) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                                <form method="POST" action="{{ route('aule.destroy', $aula) }}" class="inline" onsubmit="return confirm('Eliminare questa aula?');">
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
