@extends('layouts.app')

@section('titolo', 'Nuovo docente')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo docente</h1>

    <form method="POST" action="{{ route('docenti.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl space-y-4">
        @csrf
        @include('docenti._form')
        <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
    </form>
@endsection
