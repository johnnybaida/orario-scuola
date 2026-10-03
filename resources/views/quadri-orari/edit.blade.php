@extends('layouts.app')

@section('titolo', 'Quadro orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $quadro->nome }}</h1>

    <x-guida>
        Indica le discipline e le ore settimanali di questo quadro orario. Il totale deve coincidere con le ore
        assegnate nelle cattedre di ogni classe che lo usa. Aggiungi o rimuovi le righe, poi salva una volta sola.
    </x-guida>

    <form method="POST" action="{{ route('quadri-orari.update', $quadro) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="sezioni_extra" value="1">

        <fieldset @disabled(! auth()->user()->can('gestisci-anagrafica')) class="min-w-0 bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            <div>
                <label for="nome" class="block text-sm font-medium text-gray-700">Nome quadro</label>
                <input type="text" name="nome" id="nome" value="{{ old('nome', $quadro->nome) }}" required class="mt-1 block w-full">
            </div>

            <div>
                <h2 class="font-medium mb-2">Discipline</h2>
                <x-righe-ripetibili :righe="old('righe', $righe)" partial="quadri-orari._riga" :blocca="$discipline->isEmpty() ? 'Nessuna disciplina censita: aggiungila prima nella sezione Discipline.' : null" :dati="['discipline' => $discipline]" etichetta="Aggiungi disciplina" />
                <p class="mt-3 text-sm font-medium">Totale ore settimanali: <span data-totale="quadro">{{ $quadro->ore_totali }}</span></p>
            </div>
        </fieldset>

        @can('gestisci-anagrafica')
            <x-barra-salvataggio :annulla="route('quadri-orari.index')" />
        @endcan
    </form>
@endsection
