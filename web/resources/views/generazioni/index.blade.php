@extends('layouts.app')

@section('titolo', 'Generazioni orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Generazioni orario</h1>

    <x-guida>
        Ogni generazione lancia il solver (OR-Tools) in background per produrre un orario che rispetta i vincoli
        rigidi e minimizza le violazioni di quelli preferenziali. Il seed determina il risultato in modo
        riproducibile: stesso seed e stessi dati producono lo stesso orario. Serve un worker di coda attivo:
        puoi avviarlo e fermarlo da qui sotto. L'arresto è sicuro, il worker termina prima il job in corso.
    </x-guida>

    @php($attivoPieno = $workerAttivo && ! $workerInArresto)
    <div class="mb-4 flex items-center gap-3 rounded-lg border px-4 py-3 text-sm {{ $attivoPieno ? 'bg-green-50 border-green-200 text-green-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
        <span class="font-medium">Worker di coda: {{ $attivoPieno ? 'attivo' : ($workerInArresto ? 'in arresto' : 'fermo') }}</span>
        @can('gestisci-anagrafica')
            <form method="POST" action="{{ route($attivoPieno ? 'worker.ferma' : 'worker.avvia') }}"
                  @if ($attivoPieno) onsubmit="return confirm('Fermare il worker? Termina prima il job in corso.');" @endif>
                @csrf
                <button type="submit" class="underline cursor-pointer">{{ $attivoPieno ? 'Ferma' : 'Avvia' }}</button>
            </form>
        @endcan
        @if ($workerInArresto)
            <span>Sta terminando il job in corso: puoi già riavviarlo.</span>
        @elseif (! $workerAttivo)
            <span>Le generazioni restano in coda finché non viene avviato.</span>
        @endif
    </div>

    <x-barra-tabella>
        <x-slot:azioni>
            <a href="{{ route('prompt.index') }}" class="text-sm underline text-gray-600">Prompt per l'AI</a>
            @can('gestisci-anagrafica')
                <a href="{{ route('generazioni.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova generazione</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Stato</th>
                    <th class="px-4 py-2">Progresso</th>
                    <th class="px-4 py-2">Orario</th>
                    <th class="px-4 py-2">Seed</th>
                    <th class="px-4 py-2">Punteggio</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($generazioni as $g)
                    <tr>
                        <td class="px-4 py-2">{{ $g->id }}</td>
                        <td class="px-4 py-2">{{ $g->stato }}</td>
                        <td class="px-4 py-2">{{ $g->progresso }}%</td>
                        <td class="px-4 py-2">{{ $g->orario?->etichetta() ?? $g->nome }}</td>
                        <td class="px-4 py-2">{{ $g->seed }}</td>
                        <td class="px-4 py-2">{{ $g->orario?->punteggio }}</td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('generazioni.show', $g) }}" class="text-gray-600 hover:text-gray-900 underline">Dettaglio</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
