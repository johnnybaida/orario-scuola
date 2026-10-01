@extends('layouts.app')

@section('titolo', 'Nuova generazione')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuova generazione orario</h1>

    <form method="POST" action="{{ route('generazioni.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="time_limit_s" class="block text-sm font-medium text-gray-700">Tempo limite (secondi)</label>
            <input type="number" name="time_limit_s" id="time_limit_s" min="10" max="900" value="{{ old('time_limit_s', 120) }}" required
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label for="seed" class="block text-sm font-medium text-gray-700">Seed (vuoto = casuale)</label>
            <input type="number" name="seed" id="seed" min="0" value="{{ old('seed') }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <p class="text-xs text-gray-500">
            La generazione viene eseguita in coda: assicurati che <code>php artisan queue:work</code> sia attivo.
        </p>
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Avvia generazione</button>
    </form>
@endsection
