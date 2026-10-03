@extends('layouts.app')

@section('titolo', 'Nuova classe')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuova classe</h1>

    <form method="POST" action="{{ route('classi.store') }}">
        @csrf
        <div class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
            @include('classi._form')
        </div>
        <x-barra-salvataggio :annulla="route('classi.index')" />
    </form>
@endsection
