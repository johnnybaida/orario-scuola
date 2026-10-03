@extends('layouts.app')

@section('titolo', 'Nuova generazione')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuova generazione orario</h1>

    <form method="POST" action="{{ route('generazioni.store') }}">
        @csrf
        <div class="form-colonne bg-white border border-gray-200 rounded-lg p-6">
            <div>
                <label for="time_limit_s" class="block text-sm font-medium text-gray-700">Tempo limite (secondi)</label>
                <input type="number" name="time_limit_s" id="time_limit_s" min="10" max="900" value="{{ old('time_limit_s', 120) }}" required class="mt-1 block w-full">
            </div>
            <div>
                <label for="seed" class="block text-sm font-medium text-gray-700">Seed (vuoto = casuale)</label>
                <input type="number" name="seed" id="seed" min="0" value="{{ old('seed') }}" class="mt-1 block w-full">
            </div>
            <p class="text-xs text-gray-500">
                La generazione viene eseguita in coda: assicurati che il worker di coda sia attivo (puoi avviarlo da "Genera orario").
            </p>
        </div>
        <x-barra-salvataggio :annulla="route('generazioni.index')" etichetta="Avvia generazione" />
    </form>
@endsection
