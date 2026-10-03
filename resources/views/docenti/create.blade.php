@extends('layouts.app')

@section('titolo', 'Nuovo docente')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo docente</h1>

    <form method="POST" action="{{ route('docenti.store') }}">
        @csrf
        <div class="bg-white border border-gray-200 rounded-lg p-6 form-colonne">
            @include('docenti._form')
        </div>
        <x-barra-salvataggio :annulla="route('docenti.index')" />
    </form>
@endsection
