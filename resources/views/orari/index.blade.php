@extends('layouts.app')

@section('titolo', 'Orari')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Orari generati</h1>

    <x-guida>
        Elenco degli orari prodotti dalle generazioni. Da qui apri la griglia di una classe (modificabile) o di un
        docente (sola lettura), oppure esporti il tabellone generale in PDF.
    </x-guida>

    <x-barra-selezione />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-anagrafica')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Periodo</th>
                    <th class="px-4 py-2">Versione</th>
                    <th class="px-4 py-2">Stato</th>
                    <th class="px-4 py-2">Punteggio</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($orari as $orario)
                    <tr>
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('orari.destroy', $orario) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $orario->periodo->nome }}</td>
                        <td class="px-4 py-2">{{ $orario->versione }}</td>
                        <td class="px-4 py-2">{{ $orario->stato }}</td>
                        <td class="px-4 py-2">{{ $orario->punteggio }}</td>
                        <td class="px-4 py-2 text-right space-x-3">
                            <a href="{{ route('orari.export.generale', $orario) }}" class="text-gray-600 hover:text-gray-900 underline">Tabellone PDF</a>
                            <a href="{{ route('orari.export.classi', $orario) }}" class="text-gray-600 hover:text-gray-900 underline">Classi PDF</a>
                            <select data-ricerca class="js-vai-classe text-sm" data-base="/orari/{{ $orario->id }}/classe">
                                <option value="">Vista classe…</option>
                                @foreach ($classi as $classe)
                                    <option value="{{ $classe->id }}">{{ $classe->nomeCompleto() }}</option>
                                @endforeach
                            </select>
                            <select data-ricerca class="js-vai-classe text-sm" data-base="/orari/{{ $orario->id }}/docente">
                                <option value="">Vista docente…</option>
                                @foreach ($docenti as $docente)
                                    <option value="{{ $docente->id }}">{{ $docente->nomeCompleto() }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
