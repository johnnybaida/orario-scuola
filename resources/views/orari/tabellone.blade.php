@extends('layouts.app')

@section('titolo', 'Tabellone')

@section('contenuto')
    @php($aulaMode = $per === 'aula')
    @php($segmento = fn ($attivo) => 'px-3 py-1.5 text-sm transition-colors '.($attivo ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-50'))

    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">
            Tabellone <span class="text-base font-normal text-gray-500">· {{ $orario->etichetta() }}</span>
            <x-stato-orario :orario="$orario" class="align-middle ml-2" />
        </h1>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Come organizzare le righe: una per classe (aula fissa) o una per aula (DADA). --}}
            <div class="inline-flex overflow-hidden rounded-md border border-gray-300" role="group" aria-label="Organizzazione del tabellone">
                <a href="{{ route('orari.tabellone', [$orario, 'per' => 'classe']) }}" class="{{ $segmento(! $aulaMode) }}" @if (! $aulaMode) aria-current="true" @endif>Per classe</a>
                <a href="{{ route('orari.tabellone', [$orario, 'per' => 'aula']) }}" class="{{ $segmento($aulaMode) }} border-l border-gray-300" @if ($aulaMode) aria-current="true" @endif>Per aula</a>
            </div>
            @if ($modificabile && $aulaMode)
                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" id="conflitti-provvisori" class="rounded border-gray-300"> Conflitti provvisori
                    <x-info testo="Spento (consigliato): le modifiche che creano un conflitto (aula piena, docente occupato) vengono rifiutate. Acceso: vengono accettate e il conflitto resta segnalato nel Controllo finché non lo risolvi." />
                </label>
            @endif
            <a href="{{ route('orari.controllo', $orario) }}" class="text-sm underline text-gray-600">Controllo</a>
            <a href="{{ route('orari.export.generale', [$orario, 'per' => $per]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
        </div>
    </div>

    <x-guida>
        Tutto l'orario in una pagina, con un <strong>colore per disciplina</strong>. «<strong>Per classe</strong>» ha una riga per
        classe (vista tradizionale); «<strong>Per aula</strong>» ha una riga per aula e mostra a colpo d'occhio dove si trova ogni
        classe: è la vista più comoda con la didattica DADA. La freccia <strong>→</strong> segna le lezioni in cui la classe
        <strong>cambia aula</strong> rispetto all'ora precedente; nella vista per classe l'ultima colonna conta i cambi di
        ciascuna classe. Un riquadro con il bordo rosso ha un conflitto (passaci sopra per leggerlo).
        @if ($modificabile)
            Nella vista per aula puoi <strong>trascinare</strong> una lezione: su un'altra aula nella stessa ora per cambiarle aula,
            su un'altra ora per spostarla. Durante il trascinamento le celle si colorano (verde: si può, ambra: crea un conflitto,
            rosso: non ammesso).
        @endif
    </x-guida>

    @include('orari._controllo', ['ambito' => 'tutto l\'orario'])
    @include('orari._registro')

    @if ($righe->isEmpty() || $ore === [])
        <p class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-gray-500">Questo orario non ha ancora lezioni da mostrare.</p>
    @else
        <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <table id="tabellone" class="border-collapse text-xs" data-url-lezioni="{{ url('/orari/'.$orario->id.'/lezioni') }}"
                   data-editabile="{{ $modificabile && $aulaMode ? '1' : '0' }}">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 border border-gray-200 px-2 py-1 text-left" rowspan="2">{{ $aulaMode ? 'Aula' : 'Classe' }}</th>
                        @foreach ($giorni as $giorno)
                            <th class="border border-gray-200 border-l-2 border-l-gray-500 px-1 py-1" colspan="{{ count($ore) }}">{{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</th>
                        @endforeach
                        @unless ($aulaMode)
                            <th class="border border-gray-200 px-2 py-1" rowspan="2" title="Quante volte la classe cambia aula tra due ore consecutive">Cambi aula</th>
                        @endunless
                    </tr>
                    <tr>
                        @foreach ($giorni as $giorno)
                            @foreach ($ore as $ora)
                                <th @class(['border border-gray-200 px-1 py-0.5 font-normal', 'border-l-2 border-l-gray-500' => $loop->first])>{{ $ora }}ª</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($righe as $riga)
                        <tr>
                            <th class="sticky left-0 z-10 bg-white border border-gray-200 px-2 py-1 text-left whitespace-nowrap">
                                @if (! $aulaMode)
                                    <a href="{{ route('orari.classe', [$orario, $riga['id']]) }}" class="underline hover:text-primary">{{ $riga['etichetta'] }}</a>
                                @elseif ($riga['id'])
                                    <a href="{{ route('orari.aula', [$orario, $riga['id']]) }}" class="underline hover:text-primary">{{ $riga['etichetta'] }}</a>
                                @else
                                    {{ $riga['etichetta'] }}
                                @endif
                            </th>
                            @foreach ($giorni as $giorno)
                                @foreach ($ore as $ora)
                                    @php($s = $slot->get($giorno.'-'.$ora))
                                    <td @class(['border border-gray-200 p-0.5 align-top min-w-14 h-px', 'border-l-2 border-l-gray-500' => $loop->first])
                                        @if ($s) data-slot-id="{{ $s->id }}" @endif
                                        @if ($s && $aulaMode && $riga['id']) data-aula-id="{{ $riga['id'] }}" @endif>
                                        {{-- Il riquadro (o i riquadri, se più lezioni nella stessa cella) occupa tutta l'altezza della cella. --}}
                                        <div class="flex h-full flex-col gap-0.5">
                                        @foreach ($s ? $celle->get($s->id.'-'.$riga['id'], collect()) : [] as $lezione)
                                            @php($d = $lezione->cattedra->disciplina)
                                            @php($conflitti = $problemiPerLezione[$lezione->id] ?? [])
                                            @php($cambio = $cambi[$lezione->id] ?? null)
                                            @php($trascinabile = $modificabile && $aulaMode && ! $lezione->bloccata)
                                            <div data-lezione-id="{{ $lezione->id }}" data-slot-id="{{ $lezione->slot_id }}" draggable="{{ $trascinabile ? 'true' : 'false' }}"
                                                 style="{{ \App\Support\ColoriDiscipline::stile($colori[$d->id] ?? ['#f1f5f9', '#1e293b']) }}"
                                                 title="{{ $lezione->cattedra->classe->nomeCompleto() }} · {{ $d->nome }} · {{ $lezione->cattedra->docente->nomeCompleto() }}@if ($lezione->aula) · {{ $lezione->aula->nome }}@endif{{ $conflitti ? ' — CONFLITTO: '.implode(' — ', $conflitti) : '' }}"
                                                 class="flex flex-1 flex-col justify-center rounded px-1 py-0.5 leading-tight {{ $trascinabile ? 'cursor-grab' : '' }} {{ $conflitti ? 'ring-2 ring-red-500' : '' }}">
                                                @if ($aulaMode)
                                                    <div class="font-semibold">{{ $lezione->cattedra->classe->nomeCompleto() }}</div>
                                                    <div>{{ $d->codice }}</div>
                                                @else
                                                    <div class="font-semibold">{{ $d->codice }}</div>
                                                @endif
                                                <div class="text-[10px] opacity-80">{{ \Illuminate\Support\Str::limit($lezione->cattedra->docente->cognome, 9, '…') }}</div>
                                                @if ($cambio && ! $aulaMode)
                                                    <div class="text-[10px] font-semibold" title="Cambia aula: da {{ $cambio['da']->nome }} a {{ $cambio['a']->nome }}">→ {{ \Illuminate\Support\Str::limit($cambio['a']->nome, 12, '…') }}</div>
                                                @elseif ($cambio)
                                                    <div class="text-[10px] font-semibold" title="Cambia aula: da {{ $cambio['da']->nome }}">→ da {{ \Illuminate\Support\Str::limit($cambio['da']->nome, 7, '…') }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                        </div>
                                    </td>
                                @endforeach
                            @endforeach
                            @unless ($aulaMode)
                                <td class="border border-gray-200 px-2 py-1 text-center font-medium">{{ $cambiPerClasse[$riga['id']] ?? 0 }}</td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap gap-2 text-xs">
            @foreach ($discipline as $d)
                <span class="rounded px-2 py-0.5" style="{{ \App\Support\ColoriDiscipline::stile($colori[$d->id] ?? ['#f1f5f9', '#1e293b']) }}"><strong>{{ $d->codice }}</strong> {{ $d->nome }}</span>
            @endforeach
        </div>
    @endif
@endsection
