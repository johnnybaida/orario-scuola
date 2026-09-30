@extends('layouts.app')

@section('titolo', 'Modifica aula')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Modifica aula</h1>

    <form method="POST" action="{{ route('aule.update', $aula) }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
        @csrf
        @method('PUT')
        @include('aule._form')
        <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
    </form>
@endsection
