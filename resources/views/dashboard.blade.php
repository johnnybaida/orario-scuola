@extends('layouts.app')

@section('titolo', 'Dashboard')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Dashboard</h1>

    <x-guida>
        Punto di partenza dell'applicativo. Il percorso tipico: censisci <strong>sedi/aule</strong>,
        <strong>discipline</strong> e <strong>quadri orari</strong>, poi <strong>docenti</strong> e
        <strong>classi</strong>, assegna le <strong>cattedre</strong>, definisci eventuali <strong>vincoli</strong>
        e infine avvia una <strong>generazione</strong> dell'orario.
    </x-guida>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500">Classi</div>
            <div class="text-2xl font-semibold">{{ $nClassi }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500">Docenti</div>
            <div class="text-2xl font-semibold">{{ $nDocenti }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500">Cattedre</div>
            <div class="text-2xl font-semibold">{{ $nCattedre }}</div>
        </div>
    </div>
@endsection
