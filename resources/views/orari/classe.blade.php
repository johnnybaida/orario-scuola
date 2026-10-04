@extends('layouts.app')

@section('titolo', 'Orario '.$classe->nomeCompleto())

@section('contenuto')
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Orario {{ $classe->nomeCompleto() }} <span class="text-base font-normal text-gray-500">· {{ $orario->etichetta() }}</span> <x-stato-orario :orario="$orario" class="align-middle ml-2" /></h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('orari.export.classe', [$orario, $classe]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
            @if ($modificabile)
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" id="conflitti-provvisori" class="rounded border-gray-300"> Conflitti provvisori
                    <x-info testo="Spento (consigliato): l'editor rifiuta le modifiche che creano un conflitto con un docente o con un'aula. Acceso: le accetta e il conflitto resta segnalato in rosso nel Controllo finché non lo risolvi, per esempio spostando le lezioni dell'altra classe. Utile per scambi che coinvolgono più classi." />
                </label>
                {{-- Annulla/Ripeti su più livelli; scorciatoie Ctrl/Cmd+Z e Ctrl/Cmd+Maiusc+Z (vedi editor-griglia.js). --}}
                @php($pulsante = 'inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer disabled:text-gray-400 disabled:bg-gray-50 disabled:cursor-not-allowed')
                <form id="form-annulla" method="POST" action="{{ route('orari.annulla-ultima', $orario) }}">
                    @csrf
                    <button type="submit" @disabled(! $puoAnnullare) title="Annulla l'ultima modifica (Ctrl/Cmd+Z)" class="{{ $pulsante }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5 5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
                        Annulla
                    </button>
                </form>
                <form id="form-ripeti" method="POST" action="{{ route('orari.ripeti', $orario) }}">
                    @csrf
                    <button type="submit" @disabled(! $puoRipetere) title="Ripete l'ultima modifica annullata (Ctrl/Cmd+Maiusc+Z)" class="{{ $pulsante }}">
                        Ripeti
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5A5.5 5.5 0 0 0 4 14.5 5.5 5.5 0 0 0 9.5 20H13"/></svg>
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($modificabile)
        <x-guida>
            Trascina una lezione su un altro slot per spostarla; se lo slot è occupato, le due lezioni si scambiano.
            Usa il menu nella lezione per cambiarne docente e/o materia. Una lezione bloccata non può essere
            spostata, scambiata né modificata: sbloccala prima con il pulsante "Blocca/Sblocca".
        </x-guida>
    @endif

    @unless ($orario->modificabile())
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            Questo orario è <strong>{{ strtolower($orario->etichettaStato()) }}</strong> e si può solo consultare. Per modificarlo
            <strong>duplicalo</strong> dalla pagina Orari (nasce una nuova bozza) oppure, se ne hai il permesso, riportalo in bozza.
        </div>
    @endunless

    {{-- Stato reale (ricalcolato a ogni apertura): sparisce da solo quando i problemi vengono risolti. --}}
    @php($erroriLive = collect($problemi)->where('gravita', 'errore'))
    <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $problemi === [] ? 'bg-green-50 border-green-200 text-green-800' : ($erroriLive->isNotEmpty() ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800') }}">
        <div class="flex items-center justify-between gap-3 {{ $problemi === [] ? '' : 'mb-2' }}">
            <p class="font-medium">
                Controllo dell'orario
                @if ($problemi === [])
                    — nessun problema per questa classe
                @else
                    — {{ $erroriLive->count() }} {{ $erroriLive->count() === 1 ? 'errore' : 'errori' }}, {{ count($problemi) - $erroriLive->count() }} {{ count($problemi) - $erroriLive->count() === 1 ? 'avviso' : 'avvisi' }}
                @endif
            </p>
            <a href="{{ route('orari.controllo', $orario) }}" class="text-xs underline whitespace-nowrap">Tutto l'orario</a>
        </div>
        @if ($problemi !== [])
            <ul class="space-y-1">
                @foreach (array_slice($problemi, 0, 8) as $problema)
                    <li>
                        <span class="font-mono text-xs uppercase">[{{ $problema['gravita'] }}]</span> {{ $problema['testo'] }}
                        @foreach ($problema['classi'] as $altraId)
                            @if ($altraId !== $classe->id && $classiOrario->has($altraId))
                                <a href="{{ route('orari.classe', [$orario, $altraId]) }}" class="ml-1 whitespace-nowrap underline">Apri {{ $classiOrario[$altraId]->nomeCompleto() }}</a>
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
            @if (count($problemi) > 8)
                <p class="mt-1 text-xs">… e altri {{ count($problemi) - 8 }}: vedi «Tutto l'orario».</p>
            @endif
        @endif
    </div>

    @if ($avvisi->isNotEmpty())
        {{-- Cronologia degli esiti: NON descrive lo stato attuale (lo fa il Controllo qui sopra). Neutra, per non sembrare un allarme. --}}
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm">
            <div class="flex items-start justify-between gap-3 mb-2">
                <div>
                    <p class="font-medium text-gray-800">Registro delle modifiche</p>
                    <p class="text-xs text-gray-500">Esito dei tentativi fatti su questo orario: una modifica <strong>rifiutata</strong> non ha cambiato nulla. Lo stato attuale è nel Controllo qui sopra.</p>
                </div>
                <form method="POST" action="{{ route('orari.avvisi.azzera', $orario) }}">
                    @csrf
                    <button type="submit" class="text-xs underline text-gray-600 whitespace-nowrap">Azzera registro</button>
                </form>
            </div>
            <ul class="space-y-1">
                @foreach ($avvisi as $avviso)
                    <li class="{{ $avviso->tipo === 'errore' ? 'text-red-700' : 'text-amber-800' }}">
                        <span class="font-mono text-xs uppercase">[{{ $avviso->tipo === 'errore' ? 'modifica rifiutata' : 'avviso' }}]</span>
                        {{ $avviso->messaggio }}
                        <span class="text-gray-400 text-xs">— {{ $avviso->creato_il->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

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
