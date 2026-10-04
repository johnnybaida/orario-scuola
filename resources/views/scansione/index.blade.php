@extends('layouts.app')

@section('titolo', 'Scansione oraria')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Scansione oraria</h1>

    <x-guida>
        Orario di inizio e di fine di ogni ora di lezione, uguale per tutti i giorni. Per le ore seguite da una
        ricreazione scrivi nella colonna <strong>Ricreazione dopo</strong> quanti minuti dura (anche di durata diversa
        per ogni ricreazione); lascia vuoto dove non ce n'è. La ricreazione parte dalla fine dell'ora e deve finire
        prima dell'inizio dell'ora successiva: se cambi la fine di un'ora o la sua ricreazione, le ore che seguono si spostano da sole (poi puoi ritoccarle). Orari e ricreazioni compaiono nei PDF degli orari.
    </x-guida>

    @php($puoModificare = auth()->user()->can('gestisci-anagrafica'))
    <form method="POST" action="{{ route('scansione.update') }}" data-scansione>
        @csrf
        @method('PUT')

        <fieldset @disabled(! $puoModificare) class="min-w-0 bg-white border border-gray-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-left">
                    <tr>
                        <th class="px-4 py-2">Ora</th>
                        <th class="px-4 py-2">Inizio</th>
                        <th class="px-4 py-2">Fine</th>
                        <th class="px-4 py-2">Durata</th>
                        <th class="px-4 py-2">Ricreazione dopo (minuti)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($ore as $ordine => $ora)
                        @php($prossima = $ore->get($ordine + 1))
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $ordine }}ª</td>
                            <td class="px-4 py-2">
                                <input type="time" name="ore[{{ $ordine }}][inizio]" data-ora="{{ $ordine }}" data-campo="inizio" required aria-label="Inizio della {{ $ordine }}ª ora"
                                       value="{{ old("ore.$ordine.inizio", substr($ora->inizio, 0, 5)) }}">
                            </td>
                            <td class="px-4 py-2">
                                <input type="time" name="ore[{{ $ordine }}][fine]" data-ora="{{ $ordine }}" data-campo="fine" required aria-label="Fine della {{ $ordine }}ª ora"
                                       value="{{ old("ore.$ordine.fine", substr($ora->fine, 0, 5)) }}">
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ \App\Models\Slot::minutiTra($ora->inizio, $ora->fine) }}'</td>
                            <td class="px-4 py-2">
                                @if ($prossima)
                                    <span class="inline-flex items-center gap-2">
                                        <input type="number" min="0" max="240" step="1" inputmode="numeric" class="w-20" placeholder="nessuna"
                                               name="ore[{{ $ordine }}][ricreazione]" data-ora="{{ $ordine }}" data-campo="ricreazione" aria-label="Minuti di ricreazione dopo la {{ $ordine }}ª ora"
                                               value="{{ old("ore.$ordine.ricreazione", $ora->ricreazione_minuti) }}">
                                        <span class="text-gray-500">min</span>
                                        @if ($ora->ricreazione_minuti)
                                            <span class="text-gray-600">{{ substr($ora->fine, 0, 5) }}–{{ $ora->fineRicreazione() }}</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </fieldset>

        @if ($puoModificare)
            <x-barra-salvataggio :annulla="route('dashboard')" />
        @endif
    </form>
@endsection
