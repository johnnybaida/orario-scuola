@extends('layouts.app')

@section('titolo', 'Accedi')

@section('contenuto')
    <div class="max-w-sm mx-auto mt-16 bg-white border border-gray-200 rounded-lg p-6">
        <h1 class="text-xl font-semibold mb-6">Accedi</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" id="password" required
                       class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="ricordami"> Ricordami
            </label>

            <button type="submit" class="w-full bg-gray-900 text-white rounded py-2 text-sm font-medium hover:bg-gray-800">
                Accedi
            </button>
        </form>
    </div>
@endsection
