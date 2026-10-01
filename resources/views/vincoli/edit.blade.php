@extends('layouts.app')

@section('titolo', 'Modifica vincolo')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Modifica vincolo</h1>

    <form method="POST" action="{{ route('vincoli.update', $vincolo) }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl space-y-4">
        @csrf
        @method('PUT')
        @include('vincoli._form')
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
    </form>
@endsection
