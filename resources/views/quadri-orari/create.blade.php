@extends('layouts.app')

@section('titolo', 'Nuovo quadro orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo quadro orario</h1>

    <form method="POST" action="{{ route('quadri-orari.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
        @csrf
        <div>
            <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
            <input type="text" name="nome" id="nome" value="{{ old('nome') }}" required placeholder="Es. Tempo normale 30h"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Crea e aggiungi discipline</button>
    </form>
@endsection
