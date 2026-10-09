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

    <x-barra-tabella>
        <x-slot:azioni><x-csv-azioni lista="scansione" /></x-slot:azioni>
    </x-barra-tabella>

    @php($puoModificare = auth()->user()->can('gestisci-anagrafica'))
    @if ($ore->isEmpty())
        <x-copia-da-sede area="scansione" />
        <div class="bg-white border border-gray-200 rounded-lg p-6 space-y-3">
            <p class="text-sm text-gray-600">Questa sede non ha ancora una scansione oraria.</p>
            @if ($puoModificare)
                <form method="POST" action="{{ route('scansione.standard') }}">
                    @csrf
                    <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Crea la scansione standard</button>
                    <x-info testo="Lunedì–venerdì, 6 ore al mattino da 50 minuti con 10 minuti di ricreazione dopo la 3ª e 3 ore al pomeriggio. Poi la modifichi come serve." />
                </form>
            @endif
        </div>
    @else
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
                        <th class="px-4 py-2">Conta per il docente <x-info testo="Facoltativo: quanto vale la pausa nel monte ore del docente che la sorveglia, a scatti di 15 minuti (60 minuti = 1 ora). Con «Automatico» vale la durata arrotondata per eccesso al quarto d'ora: 50 minuti contano come 1 ora." /></th>
                        <th class="px-4 py-2">Aula della pausa <x-info testo="Facoltativo: l'aula in cui si svolge la pausa (per esempio il refettorio). Compare nei PDF accanto alla pausa. Si sceglie tra le aule di tipo «Aula per la pausa»: creale in Aule." /></th>
                        <th class="px-4 py-2">Nome della pausa <x-info testo="Facoltativo (vale anche per la pausa prima della prima ora, che finisce quando comincia la prima ora): scrivi per esempio «Mensa» per la pausa lunga prima dei rientri pomeridiani. Se resta vuoto la pausa si chiama «Ricreazione». Il nome compare nei PDF." /></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php($primaOra = $ore->first())
                    <tr class="bg-gray-50/60">
                        <td class="px-4 py-2 text-gray-600 whitespace-nowrap">Prima della {{ $ore->keys()->first() }}ª</td>
                        <td class="px-4 py-2 text-gray-600" colspan="3">
                            <span data-pausa-prima-orario>@if ($primaOra->pausa_prima_minuti){{ $primaOra->inizioPausaPrima() }}–{{ substr($primaOra->inizio, 0, 5) }}@else — @endif</span>
                        </td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center gap-2">
                                <input type="number" min="0" max="240" step="1" inputmode="numeric" class="w-20" placeholder="nessuna" data-pausa-prima-minuti
                                       name="pausa_prima[minuti]" aria-label="Minuti di pausa prima della {{ $ore->keys()->first() }}ª ora"
                                       value="{{ old('pausa_prima.minuti', $primaOra->pausa_prima_minuti) }}">
                                <span class="text-gray-500">min</span>
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <select name="pausa_prima[conteggio]" class="w-32" aria-label="Minuti conteggiati per il docente">
                                        <option value="">Automatico</option>
                                        @foreach (range(15, 240, 15) as $m)
                                            <option value="{{ $m }}" @selected((int) old('pausa_prima.conteggio', $primaOra->pausa_prima_conteggio) === $m)>{{ $m }} min</option>
                                        @endforeach
                                    </select>
                        </td>
                        <td class="px-4 py-2">
                            <select name="pausa_prima[aula]" class="w-40" aria-label="Aula della pausa" @disabled($auleInPausa->isEmpty())>
                                <option value="">Nessuna</option>
                                @foreach ($auleInPausa as $a)
                                    <option value="{{ $a->id }}" @selected((int) old("pausa_prima.aula", $primaOra->pausa_prima_aula_id) === $a->id)>{{ $a->nome }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" maxlength="40" class="w-40" placeholder="Pausa" name="pausa_prima[nome]" aria-label="Nome della pausa prima della {{ $ore->keys()->first() }}ª ora"
                                   value="{{ old('pausa_prima.nome', $primaOra->pausa_prima_nome) }}">
                        </td>
                    </tr>
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
                            <td class="px-4 py-2">
                                @if ($prossima)
                                    <select name="ore[{{ $ordine }}][conteggio]" class="w-32" aria-label="Minuti conteggiati per il docente">
                                        <option value="">Automatico</option>
                                        @foreach (range(15, 240, 15) as $m)
                                            <option value="{{ $m }}" @selected((int) old("ore.$ordine.conteggio", $ora->ricreazione_conteggio) === $m)>{{ $m }} min</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($prossima)
                                    <select name="ore[{{ $ordine }}][aula]" class="w-40" aria-label="Aula della pausa" @disabled($auleInPausa->isEmpty())>
                                        <option value="">Nessuna</option>
                                        @foreach ($auleInPausa as $a)
                                            <option value="{{ $a->id }}" @selected((int) old("ore.$ordine.aula", $ora->ricreazione_aula_id) === $a->id)>{{ $a->nome }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                @if ($prossima)
                                    <input type="text" maxlength="40" class="w-40" placeholder="Ricreazione" name="ore[{{ $ordine }}][nome]" aria-label="Nome della pausa dopo la {{ $ordine }}ª ora"
                                           value="{{ old("ore.$ordine.nome", $ora->ricreazione_nome) }}">
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
    @endif
@endsection
