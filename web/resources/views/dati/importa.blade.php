@extends('layouts.app')

@section('titolo', 'Importa dati')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">Importa dati</h1>
    <p class="mb-6 text-sm text-gray-500">Archivio creato il {{ \Illuminate\Support\Carbon::parse($manifest['creato'])->format('d/m/Y H:i') }} con la versione {{ $manifest['versione'] }}.</p>

    <form method="POST" action="{{ route('dati.importa') }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        @csrf
        <p class="text-sm text-gray-600">Scegli le tabelle da importare (tra parentesi le righe dell'archivio). Quelle spuntate vengono
            <strong>sostituite</strong> con il contenuto dell'archivio. Se una tabella dipende da altre non spuntate e i riferimenti non tornano,
            l'import si ferma e non cambia nulla. </p>
        <x-tabelle-dati :tabelle="$tabelle" :righe="$conteggi" />

        <p class="text-sm rounded bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2">
            Prima di sostituire i dati attuali conviene <a href="{{ route('dati.index') }}" class="underline">scaricarne un backup</a> (poi ricarica qui lo ZIP).
        </p>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="conferma" value="1" required> Ho capito: i dati delle tabelle spuntate saranno sostituiti.
        </label>
        <div class="flex items-center gap-4">
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Sostituisci i dati</button>
            <a href="{{ route('dati.index') }}" class="text-sm underline text-gray-600">Annulla</a>
        </div>
    </form>
@endsection
