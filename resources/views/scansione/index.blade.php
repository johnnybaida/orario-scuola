@extends('layouts.app')

@section('titolo', 'Scansione oraria')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Scansione oraria</h1>

    <x-guida>
        Orario di inizio e di fine di ogni ora di lezione, uguale per tutti i giorni. Spunta <strong>Ricreazione dopo</strong>
        sulle ore che sono seguite da una ricreazione (anche più di una): la ricreazione dura dalla fine di quell'ora
        all'inizio della successiva, quindi lascia quel tempo libero tra le due ore. Orari e ricreazioni compaiono
        nei PDF degli orari.
    </x-guida>

    @php($puoModificare = auth()->user()->can('gestisci-anagrafica'))
    <form method="POST" action="{{ route('scansione.update') }}">
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
                        <th class="px-4 py-2">Ricreazione dopo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($ore as $ordine => $ora)
                        @php($prossima = $ore->get($ordine + 1))
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $ordine }}ª</td>
                            <td class="px-4 py-2">
                                <input type="time" name="ore[{{ $ordine }}][inizio]" required aria-label="Inizio della {{ $ordine }}ª ora"
                                       value="{{ old("ore.$ordine.inizio", substr($ora->inizio, 0, 5)) }}">
                            </td>
                            <td class="px-4 py-2">
                                <input type="time" name="ore[{{ $ordine }}][fine]" required aria-label="Fine della {{ $ordine }}ª ora"
                                       value="{{ old("ore.$ordine.fine", substr($ora->fine, 0, 5)) }}">
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ \App\Models\Slot::minutiTra($ora->inizio, $ora->fine) }}'</td>
                            <td class="px-4 py-2">
                                @if ($prossima)
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" name="ore[{{ $ordine }}][ricreazione]" value="1" @checked(old("ore.$ordine.ricreazione", $ora->intervallo_dopo))>
                                        @if ($ora->intervallo_dopo)
                                            <span class="text-gray-600">{{ substr($ora->fine, 0, 5) }}–{{ substr($prossima->inizio, 0, 5) }} ({{ \App\Models\Slot::minutiTra($ora->fine, $prossima->inizio) }}')</span>
                                        @endif
                                    </label>
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
