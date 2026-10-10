@extends('layouts.app')

@section('titolo', 'Orario aula '.$aula->nome)

@section('contenuto')
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">
            Orario aula {{ $aula->nome }}
            <span class="text-base font-normal text-gray-500">· {{ $orario->etichetta() }}</span>
            <x-stato-orario :orario="$orario" class="align-middle ml-2" />
        </h1>
        <div class="flex items-center gap-3">
            <a href="{{ route('orari.export.aula', [$orario, $aula]) }}" target="_blank" rel="noopener" class="text-sm underline text-gray-600">Esporta PDF</a>
            @if ($modificabile)
                @include('orari._barra-modifica')
            @endif
        </div>
    </div>
    <p class="mb-4 text-sm text-gray-500">
        {{ $aula->sede?->nome }} · tipo {{ str_replace('_', ' ', $aula->tipo) }} · {{ $aula->capienza }} {{ $aula->capienza === 1 ? 'classe alla volta' : 'classi alla volta' }}
    </p>

    <x-guida>
        Chi c'è in questa aula a ogni ora: classe, disciplina e docente. Comprende anche le classi che hanno questa come aula
        base. È la vista più comoda con la didattica <strong>DADA</strong> (sono le classi a spostarsi) e il foglio da appendere
        alla porta. Le ore vuote sono quelle in cui l'aula è libera.
        @if ($modificabile)
            Puoi <strong>trascinare</strong> una lezione su un'altra ora: resta in quest'aula, se è libera, e si scambia con la
            lezione che la classe aveva in quell'ora (durante il trascinamento le celle si colorano: verde si può, ambra crea un
            conflitto e serve «Conflitti provvisori», rosso non è ammesso). Per cambiare aula a una lezione usa il tabellone per aula.
        @else
            Vista in sola lettura: per modificare apri la griglia della classe interessata (cliccando il nome della classe).
        @endif
    </x-guida>

    @include('orari._controllo', ['ambito' => 'questa aula'])
    @include('orari._registro')

    @php($totale = $lezioni->flatten()->count())
    <p class="mb-3 text-sm text-gray-600">{{ $totale }} {{ $totale === 1 ? 'ora occupata' : 'ore occupate' }} alla settimana.</p>

    <div class="bg-white border border-gray-200 rounded-lg">
        <table class="w-full table-fixed text-sm border-collapse" data-modifica data-modo="aula" data-url-lezioni="{{ url('/orari/'.$orario->id.'/lezioni') }}"
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
                            <td class="p-1 border border-gray-200 align-top break-words min-w-0" @if ($slot) data-slot-id="{{ $slot->id }}" data-aula-id="{{ $aula->id }}" @endif>
                                @if ($slot)
                                    @php($inCella = $lezioni->get($slot->id, collect()))
                                    @php($troppe = $inCella->pluck('cattedra.classe_id')->unique()->count() > $aula->capienza)
                                    @if ($troppe)
                                        <div class="mb-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-800">⚠ {{ $inCella->pluck('cattedra.classe_id')->unique()->count() }} classi insieme, l'aula ne ospita {{ $aula->capienza }}</div>
                                    @endif
                                    @forelse ($inCella as $lezione)
                                        @php($conflitti = $problemiPerLezione[$lezione->id] ?? [])
                                        @php($trascinabile = $modificabile && ! $lezione->bloccata)
                                        <div data-lezione-id="{{ $lezione->id }}" data-slot-id="{{ $slot->id }}" draggable="{{ $trascinabile ? 'true' : 'false' }}"
                                             @if ($conflitti) title="{{ implode(' — ', $conflitti) }}" aria-invalid="true" @endif
                                             class="mb-1 rounded px-2 py-1 text-xs border {{ $troppe || $conflitti ? 'bg-red-50 border-red-300' : ($lezione->bloccata ? 'bg-amber-100 border-amber-300' : 'bg-blue-50 border-blue-200') }} {{ $trascinabile ? 'cursor-grab' : '' }}">
                                            <a href="{{ route('orari.classe', [$orario, $lezione->cattedra->classe]) }}" class="block font-medium underline-offset-2 hover:underline">{{ $lezione->cattedra->classe->nomeCompleto() }}</a>
                                            <div class="text-gray-600">{{ $lezione->cattedra->disciplina->nome }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->docente->cognome }}</div>
                                        </div>
                                    @empty
                                        <div class="text-[10px] text-gray-300 text-center">libera</div>
                                    @endforelse
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

    @if ($laboratori)
        <p class="mt-3 text-sm text-gray-600"><strong>Laboratori pomeridiani:</strong> {{ implode(' · ', $laboratori) }}</p>
    @endif
@endsection
