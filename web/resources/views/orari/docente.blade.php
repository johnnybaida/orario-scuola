@extends('layouts.app')

@section('titolo', 'Orario '.$docente->nomeCompleto())

@section('contenuto')
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">Orario {{ $docente->nomeCompleto() }}</h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('orari.export.docente', [$orario, $docente]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
            @if ($modificabile)
                @include('orari._barra-modifica')
            @endif
        </div>
    </div>

    <x-guida>
        Tutte le ore di questo docente, nelle varie classi.
        @if ($modificabile)
            Puoi <strong>trascinare</strong> una lezione su un'altra ora: la lezione resta nella sua classe e, se in quell'ora
            la classe ha un'altra lezione, le due si scambiano. Durante il trascinamento le celle si colorano (verde: si può,
            ambra: crea un conflitto e serve «Conflitti provvisori», rosso: non ammesso). Per cambiare docente o materia di una
            lezione usa la griglia della classe.
        @else
            Vista in sola lettura: per modificare l'orario apri la griglia della classe interessata.
        @endif
        Sotto ogni lezione compare l'aula, se non è l'aula base della classe.
    </x-guida>

    @include('orari._controllo', ['ambito' => 'questo docente'])
    @include('orari._registro')

    <div class="bg-white border border-gray-200 rounded-lg">
        <table class="w-full table-fixed text-sm border-collapse" data-modifica data-modo="slot" data-url-lezioni="{{ url('/orari/'.$orario->id.'/lezioni') }}"
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
                            <td class="p-1 border border-gray-200 align-top break-words min-w-0" @if ($slot) data-slot-id="{{ $slot->id }}" @endif>
                                @if ($slot)
                                    @php($lezione = $lezioni->get($slot->id))
                                    @if ($lezione)
                                        @php($conflitti = $problemiPerLezione[$lezione->id] ?? [])
                                        <div data-lezione-id="{{ $lezione->id }}" data-slot-id="{{ $slot->id }}"
                                             draggable="{{ $modificabile && ! $lezione->bloccata ? 'true' : 'false' }}"
                                             @if ($conflitti) title="{{ implode(' — ', $conflitti) }}" aria-invalid="true" @endif
                                             class="rounded px-2 py-1 text-xs {{ $conflitti ? 'bg-red-50 border-2 border-red-400' : ($lezione->bloccata ? 'bg-amber-100 border border-amber-300' : 'bg-blue-50 border border-blue-200') }} {{ $modificabile && ! $lezione->bloccata ? 'cursor-grab' : '' }}">
                                            <div class="font-medium">{{ $lezione->cattedra->classe->nomeCompleto() }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->disciplina->nome }}</div>
                                            @if ($aulaLezione = $lezione->aulaDaMostrare())
                                                <div class="text-[10px] font-medium text-blue-800" title="Aula">{{ $aulaLezione->nome }}</div>
                                            @endif
                                            @if ($conflitti)
                                                <div class="mt-0.5 text-[10px] font-medium text-red-700">⚠ Conflitto: vedi il controllo</div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-[10px] text-gray-300 text-center">—</div>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    @if ($assistenze)
        <p class="mt-3 text-sm text-gray-600"><strong>Assistenza alle pause:</strong> {{ implode(' · ', $assistenze) }}</p>
    @endif

    @if ($laboratori)
        <p class="mt-3 text-sm text-gray-600"><strong>Laboratori pomeridiani:</strong> {{ implode(' · ', $laboratori) }}</p>
    @endif
@endsection
