@extends('layouts.app')

@section('titolo', 'Nuovo quadro orario')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo quadro orario</h1>

    <form method="POST" action="{{ route('quadri-orari.store') }}">
        @csrf
        <input type="hidden" name="sezioni_extra" value="1">
        <div class="scheda space-y-4 bg-white border border-gray-200 rounded-lg p-6">
            @include('quadri-orari._form')
        </div>
        <x-barra-salvataggio :annulla="route('quadri-orari.index')" />
    </form>
@endsection
