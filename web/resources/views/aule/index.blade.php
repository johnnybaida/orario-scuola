@extends('layouts.app')

@section('titolo', 'Aule')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Aule</h1>

    <x-guida>
        Ogni aula ha un tipo (classe, laboratorio, palestra, ...) e una capienza: quante lezioni con lo stesso
        tipo di aula richiesto possono coesistere nello stesso slot in tutto l'istituto.
        <strong>DADA</strong> (didattica per ambienti di apprendimento): le classi non hanno un'aula fissa, sono gli
        alunni a spostarsi nell'aula della disciplina. Per ogni disciplina DADA crea un'aula scegliendo
        "DADA · disciplina" come tipo: la colonna "Usata da" mostra quali discipline si svolgono in ciascuna aula.
    </x-guida>

    <x-barra-tabella>
        <x-slot:azioni>
            <x-csv-azioni lista="aule" />
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('aule.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova aula</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-anagrafica')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Nome</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Usata da</th>
                    <th class="px-4 py-2">Piano</th>
                    <th class="px-4 py-2">Capienza</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($aule as $aula)
                    <tr>
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('aule.destroy', $aula) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $aula->nome }}</td>
                        <td class="px-4 py-2">{{ \App\Enums\TipoAula::etichettaDi($aula->tipo) }}</td>
                        <td class="px-4 py-2">{{ $usataDa[$aula->tipo] ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $aula->piano ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $aula->capienza }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a data-modale href="{{ route('aule.edit', $aula) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
