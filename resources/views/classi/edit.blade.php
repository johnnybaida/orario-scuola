@extends('layouts.app')

@section('titolo', 'Classe')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $classe->nomeCompleto() }}</h1>

    <div class="grid lg:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('classi.update', $classe) }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            @csrf
            @method('PUT')
            @include('classi._form')
            <button type="submit" class="bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva</button>
        </form>

        <div class="space-y-6">
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="font-medium mb-3">Slot attivi (H5)</h2>
                <p class="text-sm text-gray-500 mb-4">Ore della scansione oraria di istituto usate da questa classe.</p>

                <form method="POST" action="{{ route('classi.slot-attivi.update', $classe) }}">
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
                                                           @checked($slotAttiviIds->contains($slot->id))>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <button type="submit" class="mt-4 bg-gray-900 text-white rounded px-4 py-2 text-sm">Salva slot attivi</button>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="font-medium mb-3">Cattedre</h2>
                <table class="w-full text-sm">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">Disciplina</th>
                            <th class="py-1">Docente</th>
                            <th class="py-1">Ore</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($classe->cattedre()->with('docente', 'disciplina')->get() as $cattedra)
                            <tr>
                                <td class="py-1">{{ $cattedra->disciplina->nome }}</td>
                                <td class="py-1">{{ $cattedra->docente->nomeCompleto() }}</td>
                                <td class="py-1">{{ $cattedra->ore }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-medium border-t border-gray-100">
                            <td class="py-1">Totale / quadro</td>
                            <td class="py-1"></td>
                            <td class="py-1">{{ $classe->cattedre()->sum('ore') }} / {{ $classe->quadroOrario->ore_totali }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection
