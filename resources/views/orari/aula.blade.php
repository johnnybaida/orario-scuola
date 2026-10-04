@extends('layouts.app')

@section('titolo', 'Orario aula '.$aula->nome)

@section('contenuto')
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <h1 class="text-xl font-semibold">
            Orario aula {{ $aula->nome }}
            <span class="text-base font-normal text-gray-500">· {{ $orario->etichetta() }}</span>
            <x-stato-orario :orario="$orario" class="align-middle ml-2" />
        </h1>
        <a href="{{ route('orari.export.aula', [$orario, $aula]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
    </div>
    <p class="mb-4 text-sm text-gray-500">
        {{ $aula->sede?->nome }} · tipo {{ str_replace('_', ' ', $aula->tipo) }} · {{ $aula->capienza }} {{ $aula->capienza === 1 ? 'classe alla volta' : 'classi alla volta' }}
    </p>

    <x-guida>
        Chi c'è in questa aula a ogni ora: classe, disciplina e docente. Comprende anche le classi che hanno questa come aula
        base. È la vista più comoda con la didattica <strong>DADA</strong> (sono le classi a spostarsi) e il foglio da appendere
        alla porta. Vista in sola lettura: per modificare apri la griglia della classe interessata (le celle sono link). Le ore
        vuote sono quelle in cui l'aula è libera.
    </x-guida>

    @php($totale = $lezioni->flatten()->count())
    <p class="mb-3 text-sm text-gray-600">{{ $totale }} {{ $totale === 1 ? 'ora occupata' : 'ore occupate' }} alla settimana.</p>

    <div class="bg-white border border-gray-200 rounded-lg">
        <table class="w-full table-fixed text-sm border-collapse">
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
                            <td class="p-1 border border-gray-200 align-top break-words min-w-0">
                                @if ($slot)
                                    @php($inCella = $lezioni->get($slot->id, collect()))
                                    @php($troppe = $inCella->pluck('cattedra.classe_id')->unique()->count() > $aula->capienza)
                                    @if ($troppe)
                                        <div class="mb-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-medium text-red-800">⚠ {{ $inCella->pluck('cattedra.classe_id')->unique()->count() }} classi insieme, l'aula ne ospita {{ $aula->capienza }}</div>
                                    @endif
                                    @forelse ($inCella as $lezione)
                                        <a href="{{ route('orari.classe', [$orario, $lezione->cattedra->classe]) }}"
                                           class="mb-1 block rounded px-2 py-1 text-xs border transition-colors {{ $troppe ? 'bg-red-50 border-red-300 hover:bg-red-100' : 'bg-blue-50 border-blue-200 hover:bg-blue-100' }}">
                                            <div class="font-medium">{{ $lezione->cattedra->classe->nomeCompleto() }}</div>
                                            <div class="text-gray-600">{{ $lezione->cattedra->disciplina->nome }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->docente->cognome }}</div>
                                        </a>
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
@endsection
