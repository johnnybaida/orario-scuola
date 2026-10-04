@extends('layouts.app')

@section('titolo', 'Nome dell\'orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-4">Nome dell'orario</h1>

    <form method="POST" action="{{ route('orari.nome', $orario) }}" class="form-colonne">
        @csrf
        @method('PUT')
        <div>
            <label for="nome" class="block text-sm font-medium text-gray-700">Nome (vuoto = «Orario v{{ $orario->versione }}»)</label>
            <input type="text" name="nome" id="nome" maxlength="120" value="{{ old('nome', $orario->nome) }}" class="mt-1 block w-full">
        </div>
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
    </form>
@endsection
