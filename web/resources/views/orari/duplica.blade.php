@extends('layouts.app')

@section('titolo', 'Duplica orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-2">Duplica «{{ $orario->etichetta() }}»</h1>
    <p class="mb-4 text-sm text-gray-600">Crea una copia in bozza con le stesse lezioni, per provare una variante o un cambio temporaneo senza toccare l'orario di partenza.</p>

    <form method="POST" action="{{ route('orari.duplica', $orario) }}" class="form-colonne">
        @csrf
        <div>
            <label for="nome" class="block text-sm font-medium text-gray-700">Nome della copia</label>
            <input type="text" name="nome" id="nome" maxlength="120" value="{{ old('nome', $orario->etichetta().' (copia)') }}" class="mt-1 block w-full"
                   placeholder="es. Settimana dell'uscita didattica">
        </div>
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Duplica</button>
    </form>
@endsection
