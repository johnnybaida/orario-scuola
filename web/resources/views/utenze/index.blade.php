@extends('layouts.app')

@section('titolo', 'Utenze')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Utenze</h1>

    <x-guida>
        Account locali con accesso al sistema. Il ruolo decide cosa si può fare: l'amministratore gestisce tutto
        (anche le utenze), il referente orario l'anagrafica e l'orario, la segreteria docenti e classi, il
        dirigente e il referente sostituzioni consultano. Per i docenti collega l'utenza alla loro anagrafica.
    </x-guida>

    <x-barra-tabella>
        <x-slot:azioni>
            <a data-modale href="{{ route('utenze.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova utenza</a>
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione tabella="users" />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    <th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Ruolo</th>
                    <th class="px-4 py-2">Docente collegato</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($utenze as $utenza)
                    <tr>
                        <td class="px-4 py-2">
                            @unless ($utenza->is(auth()->user()))
                                <input type="checkbox" class="js-sel" value="{{ route('utenze.destroy', $utenza) }}" aria-label="Seleziona">
                            @endunless
                        </td>
                        <td class="px-4 py-2">{{ $utenza->name }}</td>
                        <td class="px-4 py-2">{{ $utenza->email }}</td>
                        <td class="px-4 py-2">{{ \App\Support\Ruoli::etichetta($utenza->ruolo) }}</td>
                        <td class="px-4 py-2">{{ $utenza->docente?->nomeCompleto() }}</td>
                        <td class="px-4 py-2 text-right">
                            <a data-modale href="{{ route('utenze.edit', $utenza) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
