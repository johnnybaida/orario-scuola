@extends('layouts.app')

@section('titolo', 'Dashboard')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Dashboard</h1>

    @unless ($completa)
        <x-guida>
            Benvenuto. Usa il pulsante <strong>Aiuto</strong> in alto a destra (o il tasto F1) per leggere la guida
            all'uso dell'applicazione.
        </x-guida>
    @else
        @php($card = 'bg-white border border-gray-200 rounded-lg p-5')
        @php($puoGestire = auth()->user()->can('gestisci-anagrafica'))

        {{-- Numeri dell'istituto --}}
        {{-- Griglia a 12 colonne: ogni contatore ne occupa 3 (4 per riga, 2 sugli schermi stretti); altri contatori vanno a capo da soli. --}}
        <div class="grid grid-cols-12 gap-4 mb-6">
            <div class="{{ $card }} col-span-6 md:col-span-3">
                <div class="text-sm text-gray-500">Classi</div>
                <div class="text-2xl font-semibold">{{ $conteggi['classi'] }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $tempoScuola['normale'] ?? 0 }} a tempo normale · {{ $tempoScuola['prolungato'] ?? 0 }} prolungato</div>
            </div>
            <div class="{{ $card }} col-span-6 md:col-span-3">
                <div class="text-sm text-gray-500">Docenti</div>
                <div class="text-2xl font-semibold">{{ $conteggi['docenti'] }}</div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ $tipiPosto['comune'] ?? 0 }} comuni · {{ $tipiPosto['sostegno'] ?? 0 }} sostegno · {{ $tipiPosto['potenziamento'] ?? 0 }} potenz. · {{ $tipiPosto['irc'] ?? 0 }} IRC
                    @if ($tipiPosto['strumento'] ?? 0) · {{ $tipiPosto['strumento'] }} strumento @endif
                </div>
            </div>
            <div class="{{ $card }} col-span-6 md:col-span-3">
                <div class="text-sm text-gray-500">Cattedre</div>
                <div class="text-2xl font-semibold">{{ $conteggi['cattedre'] }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ $oreAssegnate }} ore assegnate su {{ $oreDovute }} dovute</div>
            </div>
            <div class="{{ $card }} col-span-6 md:col-span-3">
                <div class="text-sm text-gray-500">Anagrafiche</div>
                <div class="text-sm mt-1 space-y-0.5">
                    <div><a href="{{ route('sedi.index') }}" class="hover:underline"><strong>{{ $conteggi['sedi'] }}</strong> {{ $conteggi['sedi'] === 1 ? 'sede' : 'sedi' }}</a></div>
                    <div><a href="{{ route('aule.index') }}" class="hover:underline"><strong>{{ $conteggi['aule'] }}</strong> {{ $conteggi['aule'] === 1 ? 'aula' : 'aule' }}</a></div>
                    <div><a href="{{ route('discipline.index') }}" class="hover:underline"><strong>{{ $conteggi['discipline'] }}</strong> {{ $conteggi['discipline'] === 1 ? 'disciplina' : 'discipline' }}</a></div>
                    <div><a href="{{ route('quadri-orari.index') }}" class="hover:underline"><strong>{{ $conteggi['quadri'] }}</strong> {{ $conteggi['quadri'] === 1 ? 'quadro orario' : 'quadri orari' }}</a></div>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 items-start">
            {{-- Percorso di avvio: a tutta larghezza, sopra la riga di Ultimo orario e Carico dei docenti --}}
            <section class="{{ $card }} lg:col-span-2" aria-labelledby="titolo-percorso">
                <h2 id="titolo-percorso" class="font-medium mb-3">Percorso di avvio</h2>
                <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">
                    @foreach ($percorso as $i => [$etichetta, $rotta, $n])
                        <li>
                            <a href="{{ route($rotta) }}" class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 hover:bg-gray-50 transition-colors">
                                <span class="inline-flex size-5 items-center justify-center rounded-full text-xs {{ $n ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}" aria-hidden="true">{{ $n ? '✓' : $i + 1 }}</span>
                                <span class="flex-1">{{ $etichetta }}</span>
                                <span class="text-gray-500">{{ $n }}</span>
                                <span class="sr-only">{{ $n ? 'completato' : 'da fare' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- 4. Ultimo orario --}}
            <section class="{{ $card }}" aria-labelledby="titolo-orario">
                <h2 id="titolo-orario" class="font-medium mb-3">Ultimo orario</h2>
                @if ($ultimoOrario)
                    <dl class="text-sm grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 mb-3">
                        <dt class="text-gray-500">Periodo</dt><dd>{{ $ultimoOrario->periodo->nome }}</dd>
                        <dt class="text-gray-500">Versione</dt><dd>{{ $ultimoOrario->versione }}</dd>
                        <dt class="text-gray-500">Stato</dt><dd>{{ $ultimoOrario->stato }}</dd>
                        <dt class="text-gray-500">Punteggio</dt><dd>{{ $ultimoOrario->punteggio }}</dd>
                        <dt class="text-gray-500">Avvisi sulle modifiche</dt>
                        <dd>{{ $avvisiAperti ?: 'nessuno' }}@if ($avvisiAperti) <span class="text-xs text-gray-500">(nella griglia di ogni classe)</span>@endif</dd>
                    </dl>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <a href="{{ route('orari.export.generale', $ultimoOrario) }}" class="underline text-primary">Tabellone PDF</a>
                        <select data-ricerca class="js-vai-classe text-sm" data-base="/orari/{{ $ultimoOrario->id }}/classe">
                            <option value="">Vista classe…</option>
                            @foreach ($classi as $classe)
                                <option value="{{ $classe->id }}">{{ $classe->nomeCompleto() }}</option>
                            @endforeach
                        </select>
                        <select data-ricerca class="js-vai-classe text-sm" data-base="/orari/{{ $ultimoOrario->id }}/docente">
                            <option value="">Vista docente…</option>
                            @foreach ($docenti as $docente)
                                <option value="{{ $docente->id }}">{{ $docente->nomeCompleto() }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Non c'è ancora nessun orario: generalo da "Nuova generazione".</p>
                @endif
            </section>

            {{-- 6. Carico docenti --}}
            <section class="{{ $card }}" aria-labelledby="titolo-carico">
                <h2 id="titolo-carico" class="font-medium mb-1">Carico dei docenti</h2>
                <p class="text-xs text-gray-500 mb-3">Docenti con ore assegnate (cattedre e sostegno) diverse dalle ore dovute.</p>
                @if ($caricoDocenti->isEmpty())
                    <p class="text-sm text-green-700">Tutti i docenti hanno le ore dovute coperte.</p>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-gray-500 text-left">
                            <tr><th class="py-1">Docente</th><th class="py-1">Assegnate / dovute</th><th class="py-1">Differenza</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($caricoDocenti->take(8) as $riga)
                                <tr>
                                    <td class="py-1"><a href="{{ route('docenti.edit', $riga['docente']) }}" class="hover:underline">{{ $riga['docente']->nomeCompleto() }}</a></td>
                                    <td class="py-1">{{ $riga['assegnate'] }} / {{ $riga['dovute'] }}</td>
                                    <td class="py-1 {{ $riga['diff'] > 0 ? 'text-red-700' : 'text-amber-700' }}">
                                        {{ $riga['diff'] > 0 ? '+'.$riga['diff'].' (oltre le dovute)' : abs($riga['diff']).' a disposizione' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($caricoDocenti->count() > 8)
                        <p class="text-xs text-gray-500 mt-2">… e altri {{ $caricoDocenti->count() - 8 }} docenti. <a href="{{ route('docenti.index') }}" class="underline">Elenco docenti</a></p>
                    @endif
                @endif
            </section>

            {{-- 1. Pronto a generare --}}
            <section class="{{ $card }}" aria-labelledby="titolo-pronto">
                <h2 id="titolo-pronto" class="font-medium mb-3">Sei pronto a generare?</h2>
                @if (empty($problemi))
                    <p class="text-sm text-green-700">Tutto a posto: i controlli prima della generazione non trovano problemi.</p>
                @else
                    <p class="text-sm text-amber-800 mb-2">{{ count($problemi) }} {{ count($problemi) === 1 ? 'problema da correggere' : 'problemi da correggere' }} prima di generare:</p>
                    <ul class="space-y-1.5 text-sm">
                        @foreach (array_slice($problemi, 0, 8) as $problema)
                            <li class="flex gap-2">
                                <span class="text-amber-600" aria-hidden="true">•</span>
                                <span>{{ $problema['testo'] }} <a href="{{ $problema['url'] }}" class="text-primary underline whitespace-nowrap">Correggi</a></span>
                            </li>
                        @endforeach
                    </ul>
                    @if (count($problemi) > 8)
                        <p class="text-xs text-gray-500 mt-2">… e altri {{ count($problemi) - 8 }}.</p>
                    @endif
                @endif
            </section>

            {{-- 2. Generazione e worker --}}
            <section class="{{ $card }}" aria-labelledby="titolo-generazione">
                <h2 id="titolo-generazione" class="font-medium mb-3">Generazione e worker</h2>
                <div class="flex flex-wrap items-center gap-3 text-sm mb-3">
                    @php($attivoPieno = $workerAttivo)
                    <span class="font-medium {{ $attivoPieno ? 'text-green-700' : 'text-amber-700' }}">
                        Worker di coda: {{ $attivoPieno ? 'attivo' : ($workerInArresto ? 'in arresto' : 'fermo') }}
                    </span>
                    @if ($puoGestire && ! $attivoPieno)
                        <form method="POST" action="{{ route('worker.avvia') }}">
                            @csrf
                            <button type="submit" class="underline text-primary cursor-pointer">Avvia</button>
                        </form>
                    @endif
                </div>
                @if ($ultimaGenerazione)
                    <dl class="text-sm grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                        <dt class="text-gray-500">Ultima generazione</dt>
                        <dd><a href="{{ route('generazioni.show', $ultimaGenerazione) }}" class="underline">#{{ $ultimaGenerazione->id }}</a> · {{ str_replace('_', ' ', $ultimaGenerazione->stato) }}@if ($ultimaGenerazione->stato === 'in_corso') ({{ $ultimaGenerazione->progresso }}%)@endif</dd>
                        <dt class="text-gray-500">Seed</dt>
                        <dd>{{ $ultimaGenerazione->seed }}</dd>
                        @if ($ultimaGenerazione->orario)
                            <dt class="text-gray-500">Punteggio</dt>
                            <dd>{{ $ultimaGenerazione->orario->punteggio }} <span class="text-xs text-gray-500">(0 = tutti i vincoli preferenziali rispettati)</span></dd>
                        @endif
                    </dl>
                @else
                    <p class="text-sm text-gray-500">Nessuna generazione ancora.</p>
                @endif
                @if ($puoGestire)
                    <a href="{{ route('generazioni.create') }}" class="mt-4 inline-block bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuova generazione</a>
                @endif
            </section>
        </div>
    @endunless
@endsection
