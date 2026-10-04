@extends('layouts.app')

@section('titolo', 'Orario '.$classe->nomeCompleto())

@section('contenuto')
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Orario {{ $classe->nomeCompleto() }} <span class="text-base font-normal text-gray-500">· {{ $orario->etichetta() }}</span> <x-stato-orario :orario="$orario" class="align-middle ml-2" /></h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('orari.export.classe', [$orario, $classe]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
            @if ($modificabile)
                @include('orari._barra-modifica')
            @endif
        </div>
    </div>

    @if ($modificabile)
        <x-guida>
            Trascina una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si scambiano.
            Usa il menu nella lezione per cambiarne docente e/o materia. Una lezione bloccata non può essere
            spostata, scambiata né modificata: sbloccala prima con il pulsante "Blocca/Sblocca". Sotto la lezione compare
            l'aula quando non è quella della classe (sempre, con la didattica DADA): per vedere l'occupazione di un'aula usa la
            vista aula dalla pagina Orari.
        </x-guida>
    @endif

    @unless ($orario->modificabile())
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Questo orario è <strong>{{ strtolower($orario->etichettaStato()) }}</strong> e si può solo consultare. Per modificarlo
            <strong>duplicalo</strong> dalla pagina Orari (nasce una nuova bozza) oppure, se ne hai il permesso, riportalo in bozza.
        </div>
    @endunless

    @include('orari._controllo', ['ambito' => 'questa classe', 'classeCorrente' => $classe->id])

    @include('orari._registro')

    <div class="bg-white border border-gray-200 rounded-lg">
        <table class="w-full table-fixed text-sm border-collapse" id="griglia-orario" data-url-lezioni="{{ url('/orari/'.$orario->id.'/lezioni') }}"
               data-editabile="{{ $modificabile ? '1' : '0' }}">
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
                            <td class="p-1 border border-gray-200 align-top break-words min-w-0 {{ $slot && !$slotAttiviIds->contains($slot->id) ? 'bg-gray-50' : '' }}"
                                @if ($slot) data-slot-id="{{ $slot->id }}" @endif>
                                @if ($slot && $slotAttiviIds->contains($slot->id))
                                    @php($lezione = $lezioni->get($slot->id))
                                    @if ($lezione)
                                        {{-- Errore aperto su questa lezione (modifica rifiutata): riquadro rosso finché gli avvisi non vengono azzerati. --}}
                                        @php($inErrore = in_array($lezione->id, $lezioniInErrore))
                                        @php($conflitti = $problemiPerLezione[$lezione->id] ?? [])
                                        @php($inErrore = $inErrore || $conflitti !== [])
                                        <div class="rounded px-2 py-1 text-xs {{ $inErrore ? 'bg-red-50 border-2 border-red-400' : ($lezione->bloccata ? 'bg-amber-100 border border-amber-300' : 'bg-blue-50 border border-blue-200') }}"
                                             @if ($inErrore) aria-invalid="true" @endif
                                             @if ($conflitti) title="{{ implode(' — ', $conflitti) }}" @endif
                                             data-lezione-id="{{ $lezione->id }}"
                                             draggable="{{ $modificabile && ! $lezione->bloccata ? 'true' : 'false' }}">
                                            <div class="font-medium">{{ $lezione->cattedra->disciplina->nome }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->docente->cognome }}</div>
                                            {{-- L'aula si mostra se non è quella della classe, oppure se è cambiata rispetto all'ora precedente (freccia →). --}}
                                            @php($cambioAula = $cambiAula[$lezione->id] ?? null)
                                            @if ($aulaLezione = $lezione->aulaDaMostrare() ?? $cambioAula['a'] ?? null)
                                                <div class="text-[10px] font-medium text-blue-800" title="{{ $cambioAula ? 'Cambio aula: da '.$cambioAula['da']->nome : 'Aula' }}">{{ $cambioAula ? '→ ' : '' }}{{ $aulaLezione->nome }}</div>
                                            @endif
                                            @if ($conflitti)
                                                <div class="mt-0.5 text-[10px] font-medium text-red-700">⚠ Conflitto: vedi il controllo</div>
                                            @elseif ($inErrore)
                                                <div class="mt-0.5 text-[10px] font-medium text-red-700">⚠ Modifica rifiutata: vedi gli avvisi</div>
                                            @endif
                                            @if ($modificabile)
                                                @unless ($lezione->bloccata)
                                                    <select data-ricerca="compatta" autocomplete="off" class="js-cambia-cattedra w-full mt-1 text-[10px] border-gray-300 rounded" data-lezione-id="{{ $lezione->id }}" data-attuale="{{ $lezione->cattedra_id }}" draggable="false">
                                                        @foreach ($cattedre as $cattedra)
                                                            <option value="{{ $cattedra->id }}" @selected($cattedra->id === $lezione->cattedra_id)>
                                                                {{ $cattedra->disciplina->nome }} - {{ $cattedra->docente->nomeCompleto() }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endunless
                                                <button type="button" class="js-blocca-lezione text-[10px] underline text-gray-500 mt-1" draggable="false">
                                                    {{ $lezione->bloccata ? 'Sblocca' : 'Blocca' }}
                                                </button>
                                            @endif
                                            @if ($compresenze->get($slot->id))
                                                <div class="mt-1 pt-1 border-t border-blue-200 text-[10px] text-green-700">
                                                    @foreach ($compresenze->get($slot->id)->unique('docente_id') as $compresenza)
                                                        ● sostegno: {{ $compresenza->docente->nomeCompleto() }}<br>
                                                    @endforeach
                                                </div>
                                            @endif
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
