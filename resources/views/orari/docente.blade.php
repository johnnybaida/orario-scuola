@extends('layouts.app')

@section('titolo', 'Orario '.$docente->nomeCompleto())

@section('contenuto')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold">Orario {{ $docente->nomeCompleto() }}</h1>
        <a href="{{ route('orari.export.docente', [$orario, $docente]) }}" class="text-sm underline text-gray-600">Esporta PDF</a>
    </div>

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm border-collapse">
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
                            <td class="p-1 border border-gray-200 align-top">
                                @if ($slot)
                                    @php($lezione = $lezioni->get($slot->id))
                                    @if ($lezione)
                                        <div class="rounded px-2 py-1 text-xs bg-blue-50 border border-blue-200">
                                            <div class="font-medium">{{ $lezione->cattedra->classe->nomeCompleto() }}</div>
                                            <div class="text-gray-500">{{ $lezione->cattedra->disciplina->nome }}</div>
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
@endsection
