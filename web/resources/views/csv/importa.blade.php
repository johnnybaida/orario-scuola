@extends('layouts.app')

@section('titolo', 'Importa '.mb_strtolower($def['titolo']))

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Importa {{ mb_strtolower($def['titolo']) }} da CSV</h1>

    @if ($esito)
        <div class="mb-4 rounded border px-4 py-3 text-sm {{ $esito['errori'] ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-green-50 border-green-200 text-green-800' }}">
            <p>Importate {{ $esito['importate'] }} righe{{ $esito['saltate'] ? ", {$esito['saltate']} già presenti (saltate)" : '' }}{{ $esito['errori'] ? ', '.count($esito['errori']).' con errori (non importate)' : '' }}.</p>
            @if ($esito['errori'])
                <ul class="list-disc list-inside space-y-1 mt-2">
                    @foreach ($esito['errori'] as $errore)
                        <li>{{ $errore }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
        <p class="text-sm text-gray-600">
            Colonne (intestazione in prima riga, separatore virgola o punto e virgola):
            <code>{{ implode(', ', $def['colonne']) }}</code>.
            Il modo più semplice è esportare la lista, aggiungere righe e reimportare: le righe già presenti vengono saltate,
            non aggiornate. I riferimenti ad altre liste (sede, disciplina, docente, ...) si scrivono con il nome o il codice già censito.
        </p>

        <form method="POST" action="{{ route('csv.importa', $lista) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm">
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Importa</button>
            <a href="{{ route($lista.'.index') }}" class="text-sm underline text-gray-600">Torna all'elenco</a>
        </form>
    </div>
@endsection
