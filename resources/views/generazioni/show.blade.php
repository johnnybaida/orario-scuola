@extends('layouts.app')

@section('titolo', 'Generazione #'.$generazione->id)

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Generazione #{{ $generazione->id }}</h1>

    <div id="stato-generazione" data-url="{{ route('generazioni.stato', $generazione) }}" data-stato="{{ $generazione->stato }}"
         class="bg-white border border-gray-200 rounded-lg p-6 max-w-xl space-y-4">
        <div>
            <span class="text-sm text-gray-500">Stato</span>
            <div class="text-lg font-medium" data-campo="stato">{{ $generazione->stato }}</div>
        </div>

        <div>
            <span class="text-sm text-gray-500">Avanzamento</span>
            <div class="w-full bg-gray-100 rounded h-2 mt-1">
                <div class="bg-gray-900 h-2 rounded" style="width: {{ $generazione->progresso }}%" data-campo="barra"></div>
            </div>
        </div>

        <div data-campo="esito">
            @if ($generazione->stato === 'completata' && $generazione->orario)
                <p class="text-green-700 text-sm">
                    Orario generato (punteggio {{ $generazione->orario->punteggio }}).
                </p>
            @elseif ($generazione->stato === 'infattibile')
                <div class="text-red-700 text-sm">
                    <p class="font-medium mb-1">Non è stato possibile generare un orario valido:</p>
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($generazione->diagnostica ?? [] as $riga)
                            <li>{{ $riga }}</li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($generazione->stato === 'fallita')
                <p class="text-red-700 text-sm">Errore tecnico durante la generazione.</p>
            @endif
        </div>
    </div>
@endsection
