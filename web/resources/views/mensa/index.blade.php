@extends('layouts.app')

@section('titolo', 'Mensa')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Mensa</h1>

    <x-guida>
        La mensa è una <strong>pausa</strong> della scansione oraria, non una materia. Per configurarla: 1) in <em>Scansione oraria</em> spunta «È la mensa»
        sulla pausa del pranzo (con durata, aula e quanto vale per i docenti); 2) nel <em>quadro orario</em> del tempo prolungato scrivi le «Ore di mensa»;
        3) una classe va in mensa nei giorni di <em>rientro</em>, cioè quando ha ore dopo la pausa (<em>Slot attivi</em> della classe); 4) qui sotto
        indichi chi sorveglia, classe per classe e giorno per giorno. Le ore di sorveglianza contano nel monte ore del docente una volta sola per giorno.
    </x-guida>

    <section class="mb-6 rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="font-medium mb-3">Cosa manca?</h2>
        <ul class="space-y-1.5 text-sm">
            @foreach ($controlli as $c)
                <li class="flex gap-2">
                    <span class="{{ $c['ok'] ? 'text-green-600' : 'text-amber-600' }}" aria-hidden="true">{{ $c['ok'] ? '✓' : '●' }}</span>
                    <span class="{{ $c['ok'] ? 'text-gray-700' : 'text-amber-800' }}">{{ $c['testo'] }}@if ($c['url'] && ! $c['ok']) <a href="{{ $c['url'] }}" class="text-primary underline whitespace-nowrap">Correggi</a>@endif</span>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($pause->isNotEmpty())
        <form method="POST" action="{{ route('mensa.update') }}" data-mensa>
            @csrf
            @method('PUT')

            @foreach ($pause as $ordine => $pausa)
                @php($daMostrare = $classi->filter(fn ($c) => $giorniDi($c, $ordine)))
                @php($giorni = $daMostrare->flatMap(fn ($c) => $giorniDi($c, $ordine))->unique()->sort()->values())
                <fieldset @disabled(! $puoModificare) class="mb-6 min-w-0 rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="font-medium">{{ $pausa['nome'] }} <span class="font-normal text-gray-500">{{ $pausa['da'] }}–{{ $pausa['a'] }} ({{ $pausa['minuti'] }}')</span></h2>
                    <p class="mb-4 text-sm text-gray-500">
                        @if ($pausa['aula']) Aula: {{ $pausa['aula'] }}. @endif
                        Vale {{ \App\Services\AssistenzaPause::formatta($pausa['conteggio'] / 60) }} ore per ogni docente che la sorveglia.
                        @if ($daMostrare->isEmpty()) Nessuna classe ha ore dopo questa pausa: spunta il rientro nella scheda della classe. @endif
                    </p>

                    @if ($daMostrare->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-left text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2">Classe</th>
                                        @foreach ($giorni as $giorno)
                                            <th class="px-3 py-2">{{ \App\Models\Slot::GIORNI[$giorno] ?? $giorno }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($daMostrare as $classe)
                                        @php($suoiGiorni = $giorniDi($classe, $ordine))
                                        <tr>
                                            <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $classe->nomeCompleto() }}</td>
                                            @foreach ($giorni as $giorno)
                                                <td class="px-3 py-2 align-top">
                                                    @if (in_array($giorno, $suoiGiorni, true))
                                                        @php($scelti = $assegnazioni[$ordine][$giorno][$classe->id] ?? [])
                                                        <div class="flex flex-col items-start gap-1" data-cella-mensa>
                                                            @foreach ([...$scelti, ''] as $scelto)
                                                                <select name="celle[{{ $ordine }}][{{ $giorno }}][{{ $classe->id }}][]" data-ricerca data-svuotabile class="w-44 text-sm" aria-label="Docente della mensa, {{ $classe->nomeCompleto() }}, {{ \App\Models\Slot::GIORNI[$giorno] ?? $giorno }}">
                                                                    <option value="">— docente —</option>
                                                                    @foreach ($docenti as $docente)
                                                                        <option value="{{ $docente->id }}" @selected((string) $scelto === (string) $docente->id)>{{ $docente->nomeCompleto() }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="text-gray-300">—</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </fieldset>
            @endforeach

            @if ($puoModificare)
                <x-barra-salvataggio :annulla="route('dashboard')" />
            @endif
        </form>
    @else
        <p class="text-sm text-gray-600">Quando una pausa sarà segnata come mensa, qui potrai assegnare i docenti.</p>
    @endif
@endsection
