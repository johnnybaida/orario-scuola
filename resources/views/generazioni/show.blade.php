@extends('layouts.app')

@section('titolo', 'Generazione #'.$generazione->id)

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Generazione #{{ $generazione->id }}</h1>

    <x-guida>
        Questa pagina si aggiorna da sola ogni due secondi finché la generazione è in corso. Se resta bloccata
        su "in coda", il worker (<code>php artisan queue:work</code>) non è attivo.
    </x-guida>

    <div id="stato-generazione" data-url="{{ route('generazioni.stato', $generazione) }}" data-stato="{{ $generazione->stato }}"
         class="bg-white border border-gray-200 rounded-lg p-6 max-w-xl space-y-4">
        <div>
            <span class="text-sm text-gray-500">Stato</span>
            <div class="text-lg font-medium" data-campo="stato">{{ $generazione->stato }}</div>
        </div>

        <div>
            <span class="text-sm text-gray-500">Avanzamento</span>
            <div class="w-full bg-gray-100 rounded h-2 mt-1">
                <div class="bg-primary h-2 rounded" style="width: {{ $generazione->progresso }}%" data-campo="barra"></div>
            </div>
        </div>

        <div data-campo="esito">
            @if ($generazione->stato === 'completata' && $generazione->orario)
                <p class="text-green-700 text-sm">
                    Orario generato (punteggio {{ $generazione->orario->punteggio }}).
                    <a href="{{ route('orari.index') }}" class="underline">Apri gli orari</a>
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
