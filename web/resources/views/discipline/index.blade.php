@extends('layouts.app')

@section('titolo', 'Discipline')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Discipline</h1>

    <x-guida>
        Catalogo delle materie insegnate, con la classe di concorso abilitante e l'eventuale tipo di aula richiesto
        (es. palestra per Scienze motorie). In modalità DADA la disciplina si svolge in un'aula dedicata dove si
        spostano gli alunni: crea l'aula in "Aule" scegliendo "DADA · disciplina" e il collegamento è automatico. "Sotto-disciplina di" collega materie insegnate dallo stesso docente
        (es. Storia e Geografia sotto Italiano), solo a scopo informativo.
    </x-guida>

    <x-copia-da-sede area="discipline" />

    <x-barra-tabella>
        <x-slot:azioni>
            <x-csv-azioni lista="discipline" />
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('discipline.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova disciplina</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione tabella="discipline" />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-anagrafica')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Codice</th>
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Classe di concorso</th>
                    <th class="px-4 py-2">Aula richiesta</th>
                    <th class="px-4 py-2">Sotto-disciplina di</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($discipline as $disciplina)
                    <tr>
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('discipline.destroy', $disciplina) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2 font-mono">{{ $disciplina->codice }}</td>
                        <td class="px-4 py-2">{{ $disciplina->nome }}</td>
                        <td class="px-4 py-2">{{ $disciplina->classe_concorso }}</td>
                        <td class="px-4 py-2">{{ collect($disciplina->tipiAmmessi())->map(fn ($t) => \App\Enums\TipoAula::etichettaDi($t))->implode(', ') }}</td>
                        <td class="px-4 py-2">{{ $disciplina->padre?->nome }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a data-modale href="{{ route('discipline.edit', $disciplina) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
