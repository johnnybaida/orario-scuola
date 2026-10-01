@extends('layouts.app')

@section('titolo', 'Nuova cattedra')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuova cattedra</h1>

    <form method="POST" action="{{ route('cattedre.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-lg space-y-4">
        @csrf
        @include('cattedre._form')
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
    </form>
@endsection
