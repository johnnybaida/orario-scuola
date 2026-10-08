@extends('layouts.app')

@section('titolo', 'Dati')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Dati: esporta e importa</h1>

    <x-guida>
        Esporta tutti i dati della scuola (o solo alcune tabelle) in un file ZIP, per fare un <strong>backup</strong> o per
        trasferirli su un'altra installazione. Con <strong>Importa</strong> carichi uno ZIP e scegli quali tabelle caricare:
        i dati attuali di quelle tabelle vengono <strong>sostituiti</strong>. Il registro delle attività non è compreso.
        Le utenze (account e password) non sono comprese.
    </x-guida>

    <form method="POST" action="{{ route('dati.esporta') }}" class="bg-white border border-gray-200 rounded-lg p-6 mb-6 space-y-4">
        @csrf
        <h2 class="font-medium">Esporta</h2>
        <x-tabelle-dati :tabelle="$tabelle" />
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Scarica lo ZIP</button>
    </form>

    <form method="POST" action="{{ route('dati.anteprima') }}" enctype="multipart/form-data" class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        @csrf
        <h2 class="font-medium">Importa</h2>
        <p class="text-sm text-gray-600">Carica uno ZIP esportato da questa applicazione. Nel passo successivo scegli cosa importare e confermi: nulla cambia prima.</p>
        <input type="file" name="file" accept=".zip,application/zip" required class="block w-full text-sm">
        <button type="submit" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50 transition-colors cursor-pointer">Carica e controlla</button>
    </form>
@endsection
