@extends('layouts.app')

@section('titolo', 'Nuovo laboratorio')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo laboratorio</h1>

    <form method="POST" action="{{ route('laboratori.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 form-colonne">
        @csrf
        @include('laboratori._form')
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
    </form>
@endsection
