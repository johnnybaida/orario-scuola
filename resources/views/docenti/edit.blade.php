@extends('layouts.app')

@section('titolo', 'Docente')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $docente->nomeCompleto() }}</h1>

    <x-guida>
        La griglia delle indisponibilità blocca gli slot in cui il docente non può avere lezione (es. part-time,
        servizio in un'altra scuola): il generatore automatico e l'editor manuale li rispettano sempre.
    </x-guida>

    <div class="grid lg:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('docenti.update', $docente) }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            @csrf
            @method('PUT')
            @include('docenti._form')
            <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
        </form>

        <div class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="font-medium mb-3">Indisponibilità (H6/T1)</h2>
                <p class="text-sm text-gray-500 mb-4">Seleziona gli slot in cui il docente non può avere lezione.</p>

                <form method="POST" action="{{ route('docenti.indisponibilita.update', $docente) }}">
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse">
                            <thead>
                                <tr>
                                    <th class="p-1"></th>
                                    @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab'] as $i => $g)
                                        @if (isset($slotPerGiorno[$i + 1]))
                                            <th class="p-1 text-center">{{ $g }}</th>
                                        @endif
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php($maxOrdine = $slotPerGiorno->flatten()->max('ordine'))
                                @for ($ordine = 1; $ordine <= $maxOrdine; $ordine++)
                                    <tr>
                                        <td class="p-1 text-gray-500">{{ $ordine }}ª</td>
                                        @foreach ($slotPerGiorno as $giorno => $slotGiorno)
                                            @php($slot = $slotGiorno->firstWhere('ordine', $ordine))
                                            <td class="p-1 text-center">
                                                @if ($slot)
                                                    <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}"
                                                           @checked($indisponibiliIds->contains($slot->id))>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="mt-4 bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva indisponibilità</button>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="font-medium mb-3">Cattedre</h2>
                <table class="w-full text-sm">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">Classe</th>
                            <th class="py-1">Disciplina</th>
                            <th class="py-1">Ore</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($docente->cattedre()->with('classe', 'disciplina')->get() as $cattedra)
                            <tr>
                                <td class="py-1">{{ $cattedra->classe->nomeCompleto() }}</td>
                                <td class="py-1">{{ $cattedra->disciplina->nome }}</td>
                                <td class="py-1">{{ $cattedra->ore }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-medium border-t border-gray-100">
                            <td class="py-1" colspan="2">Totale / dovute</td>
                            <td class="py-1">{{ $docente->cattedre()->sum('ore') }} / {{ $docente->ore_dovute }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection
