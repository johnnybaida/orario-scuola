@extends('layouts.app')

@section('titolo', 'Orario '.$classe->nomeCompleto())

@section('contenuto')
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Orario {{ $classe->nomeCompleto() }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('orari.export.classe', [$orario, $classe]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
            @can('gestisci-anagrafica')
                <form method="POST" action="{{ route('orari.annulla-ultima', $orario) }}">
                    @csrf
                    <button type="submit" class="text-sm underline text-gray-600">Annulla ultima modifica</button>
                </form>
            @endcan
        </div>
    </div>

    @can('gestisci-anagrafica')
        <x-guida>
            Trascina una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si scambiano.
            Usa il menu nella lezione per cambiarne docente e/o materia. Una lezione bloccata non può essere
            spostata, scambiata né modificata: sbloccala prima con il pulsante "Blocca/Sblocca".
        </x-guida>
    @endcan

    @if ($avvisi->isNotEmpty())
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $avvisi->contains('tipo', 'errore') ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200' }}">
            <div class="flex items-center justify-between mb-2">
                <p class="font-medium {{ $avvisi->contains('tipo', 'errore') ? 'text-red-800' : 'text-amber-800' }}">
                    Avvisi sulle modifiche a questo orario
                </p>
                <form method="POST" action="{{ route('orari.avvisi.azzera', $orario) }}">
                    @csrf
                    <button type="submit" class="text-xs underline text-gray-600">Azzera avvisi</button>
                </form>
            </div>
            <ul class="space-y-1">
                @foreach ($avvisi as $avviso)
                    <li class="{{ $avviso->tipo === 'errore' ? 'text-red-700' : 'text-amber-800' }}">
                        <span class="font-mono text-xs uppercase">[{{ $avviso->tipo }}]</span>
                        {{ $avviso->messaggio }}
                        <span class="text-gray-400 text-xs">— {{ $avviso->creato_il->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm border-collapse" id="griglia-orario" data-url-lezioni="{{ url('/orari/'.$orario->id.'/lezioni') }}"
               data-editabile="{{ auth()->user()->can('gestisci-anagrafica') ? '1' : '0' }}">
            <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="p-2 border border-gray-200 w-16">Ora</th>
                    @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'] as $i => $nome)
                        @if (isset($slotPerGiorno[$i + 1]))
                            <th class="p-2 border border-gray-200">{{ $nome }}</th>
                        @endif
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php($maxOrdine = $slotPerGiorno->flatten()->max('ordine'))
                @for ($ordine = 1; $ordine <= $maxOrdine; $ordine++)
                    <tr>
                        <td class="p-2 border border-gray-200 text-center text-gray-500 font-medium">{{ $ordine }}ª</td>
                        @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                            @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                            <td class="p-1 border border-gray-200 align-top {{ $slot && !$slotAttiviIds->contains($slot->id) ? 'bg-gray-50' : '' }}"
                                @if ($slot) data-slot-id="{{ $slot->id }}" @endif>
                                @if ($slot && $slotAttiviIds->contains($slot->id))
                                    @php($lezione = $lezioni->get($slot->id))
                                    @if ($lezione)
                                        <div class="rounded px-2 py-1 text-xs {{ $lezione->bloccata ? 'bg-amber-100 border border-amber-300' : 'bg-blue-50 border border-blue-200' }}"
                                             data-lezione-id="{{ $lezione->id }}"
                                             draggable="{{ auth()->user()->can('gestisci-anagrafica') && ! $lezione->bloccata ? 'true' : 'false' }}">
                                            <div class="font-medium">{{ $lezione->cattedra->disciplina->nome }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->docente->cognome }}</div>
                                            @can('gestisci-anagrafica')
                                                @unless ($lezione->bloccata)
                                                    <select class="js-cambia-cattedra w-full mt-1 text-[10px] border-gray-300 rounded" data-lezione-id="{{ $lezione->id }}" draggable="false">
                                                        @foreach ($cattedre as $cattedra)
                                                            <option value="{{ $cattedra->id }}" @selected($cattedra->id === $lezione->cattedra_id)>
                                                                {{ $cattedra->disciplina->nome }} - {{ $cattedra->docente->cognome }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endunless
                                                <button type="button" class="js-blocca-lezione text-[10px] underline text-gray-500 mt-1" draggable="false">
                                                    {{ $lezione->bloccata ? 'Sblocca' : 'Blocca' }}
                                                </button>
                                            @endcan
                                        </div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
@endsection
