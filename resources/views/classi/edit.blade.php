@extends('layouts.app')

@section('titolo', 'Classe')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">{{ $classe->nomeCompleto() }}</h1>

    <x-guida>
        Gli "slot attivi" sono le ore della scansione settimanale che questa classe usa davvero: tutte le classi a
        tempo normale usano solo il mattino, quelle a tempo prolungato anche i pomeriggi di rientro. Il generatore
        copre esattamente questi slot (né di più, né di meno).
    </x-guida>

    <div class="grid lg:grid-cols-2 gap-6">
        <form method="POST" action="{{ route('classi.update', $classe) }}" class="bg-white border border-gray-200 rounded-lg p-6 space-y-4">
            @csrf
            @method('PUT')
            @include('classi._form')
            <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva</button>
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

                    <button type="submit" class="mt-4 bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Salva slot attivi</button>
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

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <h2 class="font-medium mb-1">Sostegno</h2>
                <x-guida>
                    Gli alunni non sono censiti: ogni fabbisogno è identificato solo da un codice anonimo (es.
                    "1B-S1") e dalle ore settimanali di sostegno. Le ore dei docenti assegnati devono coprire la
                    somma dei fabbisogni (modalità "per alunno") o il fabbisogno più alto (modalità "per classe"):
                    il generatore programma le compresenze di conseguenza.
                </x-guida>

                <form method="POST" action="{{ route('sostegno.conteggio.update', $classe) }}" class="mb-4 flex items-center gap-2">
                    @csrf
                    @method('PUT')
                    <label for="conteggio_sostegno" class="text-sm text-gray-700">Conteggio ore</label>
                    <select name="conteggio_sostegno" id="conteggio_sostegno" onchange="this.form.submit()"
                            class="text-sm rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                        <option value="" @selected(is_null($classe->conteggio_sostegno))>Default istituto ({{ \App\Models\Impostazioni::correnti()->conteggio_sostegno }})</option>
                        <option value="per_alunno" @selected($classe->conteggio_sostegno === 'per_alunno')>Per alunno</option>
                        <option value="per_classe" @selected($classe->conteggio_sostegno === 'per_classe')>Per classe</option>
                    </select>
                </form>

                <h3 class="text-sm font-medium text-gray-700 mb-2">Fabbisogni</h3>
                <table class="w-full text-sm mb-3">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">Codice</th>
                            <th class="py-1">Ore/sett.</th>
                            <th class="py-1">Docente unico</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($fabbisogniSostegno as $fabbisogno)
                            <tr>
                                <td class="py-1 font-mono">{{ $fabbisogno->codice_anonimo }}</td>
                                <td class="py-1">{{ $fabbisogno->ore_settimanali }}</td>
                                <td class="py-1">{{ $fabbisogno->docente_unico ? 'Sì' : 'No' }}</td>
                                <td class="py-1 text-right">
                                    <form method="POST" action="{{ route('sostegno.fabbisogni.destroy', [$classe, $fabbisogno]) }}" onsubmit="return confirm('Rimuovere questo fabbisogno?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 underline text-xs">Rimuovi</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('sostegno.fabbisogni.store', $classe) }}" class="flex flex-wrap items-end gap-2 mb-6">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">Codice anonimo</label>
                        <input type="text" name="codice_anonimo" placeholder="es. 1B-S1" required class="text-sm rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Ore/sett.</label>
                        <input type="number" name="ore_settimanali" min="1" max="40" required class="w-20 text-sm rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <label class="flex items-center gap-1 text-xs text-gray-600 pb-2">
                        <input type="checkbox" name="docente_unico" value="1"> Docente unico
                    </label>
                    <button type="submit" class="bg-primary text-white rounded px-3 py-1.5 text-xs hover:bg-primary/90 transition-colors cursor-pointer">Aggiungi</button>
                </form>

                <h3 class="text-sm font-medium text-gray-700 mb-2">Docenti di sostegno assegnati</h3>
                <table class="w-full text-sm mb-3">
                    <thead class="text-gray-500 text-left">
                        <tr>
                            <th class="py-1">Docente</th>
                            <th class="py-1">Ore/sett.</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($assegnazioniSostegno as $assegnazione)
                            <tr>
                                <td class="py-1">{{ $assegnazione->docente->nomeCompleto() }}</td>
                                <td class="py-1">{{ $assegnazione->ore }}</td>
                                <td class="py-1 text-right">
                                    <form method="POST" action="{{ route('sostegno.assegnazioni.destroy', [$classe, $assegnazione]) }}" onsubmit="return confirm('Rimuovere questa assegnazione?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 underline text-xs">Rimuovi</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-medium border-t border-gray-100">
                            <td class="py-1">Totale ore assegnate / richieste</td>
                            <td class="py-1">{{ $assegnazioniSostegno->sum('ore') }} / {{ $fabbisogniSostegno->sum('ore_settimanali') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <form method="POST" action="{{ route('sostegno.assegnazioni.store', $classe) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label class="block text-xs text-gray-500">Docente</label>
                        <select name="docente_id" required class="text-sm rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                            @foreach ($docentiSostegno as $docente)
                                <option value="{{ $docente->id }}">{{ $docente->nomeCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">Ore/sett.</label>
                        <input type="number" name="ore" min="1" max="40" required class="w-20 text-sm rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                    </div>
                    <button type="submit" class="bg-primary text-white rounded px-3 py-1.5 text-xs hover:bg-primary/90 transition-colors cursor-pointer">Aggiungi</button>
                </form>
            </div>
        </div>
    </div>
@endsection
