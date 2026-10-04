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
                <label for="nome" class="block text-sm font-medium text-gray-700">Nome dell'orario (facoltativo)</label>
                <input type="text" name="nome" id="nome" maxlength="120" value="{{ old('nome') }}" class="mt-1 block w-full" placeholder="es. Orario di base">
            </div>
            <div>
                <label for="seed" class="flex items-center gap-1.5 text-sm font-medium text-gray-700">Seed
                    <x-info testo="Il seed decide il risultato: lo stesso seed con gli stessi dati dà lo stesso orario. Scegli uno dei seed già usati per riprodurre quell'orario." />
                </label>
                <select name="seed" id="seed" class="mt-1 block w-full">
                    <option value="">Casuale (un orario diverso a ogni generazione)</option>
                    @foreach ($seedUsati as $s)
                        <option value="{{ $s['seed'] }}" @selected((string) old('seed') === (string) $s['seed'])>Come «{{ $s['etichetta'] }}»</option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-gray-500">
                La generazione viene eseguita in coda da un worker: se è fermo, lo avvio io quando premi "Avvia generazione".
            </p>
        </div>
        <x-barra-salvataggio :annulla="route('generazioni.index')" etichetta="Avvia generazione" />
    </form>
@endsection
