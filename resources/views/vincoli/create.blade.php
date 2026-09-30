@extends('layouts.app')

@section('titolo', 'Nuovo vincolo')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Nuovo vincolo</h1>

    <form method="POST" action="{{ route('vincoli.store') }}" class="bg-white border border-gray-200 rounded-lg p-6 max-w-2xl space-y-4">
        @csrf
        @include('vincoli._form')
        <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
    </form>
@endsection
