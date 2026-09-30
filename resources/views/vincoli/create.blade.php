@extends('layouts.app')

@section('titolo', 'Nuovo vincolo')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo vincolo</h1>

    <x-guida>
        Scegli il tipo: i campi sotto cambiano di conseguenza. "Ambito globale" applica il vincolo a tutte le
        classi/docenti; scegli classi o docenti specifici per restringerlo. Con severità "preferenziale" imposta
        anche un peso: più alto, più il solver cerca di evitarne la violazione.
    </x-guida>

    <form method="POST" action="{{ route('vincoli.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl space-y-4">
        @csrf
        @include('vincoli._form')
        <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
    </form>
@endsection
