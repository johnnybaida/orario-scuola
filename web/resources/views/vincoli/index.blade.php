@extends('layouts.app')

@section('titolo', 'Vincoli')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Vincoli</h1>

    <x-guida>
        Regole aggiuntive per la generazione automatica dell'orario, oltre a quelle di sistema sempre attive
        (es. un docente non può essere in due posti contemporaneamente). Un vincolo <strong>rigido</strong> deve
        essere rispettato sempre, pena l'infattibilità; uno <strong>preferenziale</strong> ha un peso (1-100) e
        viene violato solo se non c'è alternativa migliore.
    </x-guida>

    <x-copia-da-sede area="vincoli" />

    <x-barra-tabella>
        <form method="GET">
            <select name="tipo" class="rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary" onchange="this.form.submit()">
                <option value="">Tutti i tipi</option>
                @foreach ($etichette as $tipo => $etichetta)
                    <option value="{{ $tipo }}" @selected($filtroTipo === $tipo)>{{ $etichetta }}</option>
                @endforeach
            </select>
        </form>
        <x-slot:azioni>
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('vincoli.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuovo vincolo</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione tabella="vincoli" />

    <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-left">
                <tr>
                    @can('gestisci-anagrafica')<th class="px-4 py-2 w-8"><input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti"></th>@endcan
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Ambito</th>
                    <th class="px-4 py-2">Disciplina</th>
                    <th class="px-4 py-2">Severità</th>
                    <th class="px-4 py-2">Peso</th>
                    <th class="px-4 py-2">Attivo</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($vincoli as $vincolo)
                    <tr>
                        @can('gestisci-anagrafica')<td class="px-4 py-2"><input type="checkbox" class="js-sel" value="{{ route('vincoli.destroy', $vincolo) }}" aria-label="Seleziona"></td>@endcan
                        <td class="px-4 py-2">{{ $etichette[$vincolo->tipo] ?? $vincolo->tipo }}</td>
                        <td class="px-4 py-2">{{ $vincolo->ambito_livello }}</td>
                        <td class="px-4 py-2">
                            @if (! in_array($vincolo->tipo, ['D1_BLOCCO_MIN_CONSECUTIVO', 'D3_MAX_ORE_GIORNO', 'D6_FASCIA_ORARIA', 'D12_BLOCCO_MAX_CONSECUTIVO', 'D13_DISCIPLINA_SEGUITA'], true))
                                <span class="text-gray-400">—</span>
                            @else
                                {{ collect(\App\Constraints\DisciplineVincolo::ids($vincolo->parametri ?? []))->map(fn ($id) => $discipline[$id] ?? '?')->implode(', ') ?: 'Tutte' }}
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $vincolo->severita }}</td>
                        <td class="px-4 py-2">{{ $vincolo->peso }}</td>
                        <td class="px-4 py-2">{{ $vincolo->attivo ? 'Sì' : 'No' }}</td>
                        <td class="px-4 py-2 text-right space-x-2">
                            @can('gestisci-anagrafica')
                                <a data-modale href="{{ route('vincoli.edit', $vincolo) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
