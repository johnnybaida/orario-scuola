@extends('layouts.app')

@section('titolo', 'Importa docenti')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Importa docenti da CSV</h1>

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <p class="text-sm text-gray-600">
            Colonne attese (intestazione in prima riga): <code>nome, cognome, email, tipo_contratto, tipo_posto, regime, ore_dovute</code>.
            Solo <code>nome</code> e <code>cognome</code> sono obbligatorie; gli altri campi hanno valori di default.
        </p>

        <form method="POST" action="{{ route('docenti.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm">
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Importa</button>
        </form>
    </div>
@endsection
