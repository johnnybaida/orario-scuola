@extends('layouts.app')

@section('titolo', 'Orari')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Orari generati</h1>

    <x-guida>
        Elenco degli orari prodotti dalle generazioni. Da qui apri la griglia di una classe o di un docente, oppure
        esporti i PDF. Ogni orario ha uno <strong>stato</strong>: bozza → in revisione → approvato → pubblicato → archiviato.
        Solo la <strong>bozza</strong> si modifica (la griglia di una classe è modificabile soltanto allora); con
        <strong>Duplica</strong> crei una copia in bozza per provare una variante. Si eliminano solo gli orari in bozza
        o archiviati.
    </x-guida>

    <x-barra-selezione />

    @php($puoEliminare = auth()->user()->can('gestisci-anagrafica'))
    @php($bordo = ['bozza' => 'border-l-gray-300', 'in_revisione' => 'border-l-amber-400', 'approvato' => 'border-l-blue-400', 'pubblicato' => 'border-l-green-500', 'archiviato' => 'border-l-gray-400'])
    @php($pulsante = 'inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer')
    @php($principale = 'inline-flex items-center rounded-md bg-primary px-3 py-1.5 text-sm text-white hover:opacity-90 transition-opacity cursor-pointer')

    @if ($orari->isEmpty())
        <p class="rounded-lg border border-gray-200 bg-white px-4 py-6 text-center text-gray-500">Nessun orario: generalo da <strong>Genera orario</strong>.</p>
    @else
        @if ($puoEliminare)
            <label class="mb-3 inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" class="js-sel-tutti" aria-label="Seleziona tutti gli orari"> Seleziona tutti
            </label>
        @endif

        <div class="grid gap-4">
            @foreach ($orari as $orario)
                @php($consentite = \App\Support\StatiOrario::consentite($orario, auth()->user()))
                <article data-riga class="rounded-lg border border-gray-200 border-l-4 {{ $bordo[$orario->stato] ?? 'border-l-gray-300' }} bg-white">
                    <header class="flex flex-wrap items-start gap-3 border-b border-gray-100 px-4 py-3">
                        @if ($puoEliminare)
                            <input type="checkbox" class="js-sel mt-1" value="{{ route('orari.destroy', $orario) }}" aria-label="Seleziona l'orario {{ $orario->periodo->nome }} versione {{ $orario->versione }}">
                        @endif
                        <div class="min-w-0 flex-1">
                            <h2 class="flex flex-wrap items-center gap-2 font-semibold">
                                {{ $orario->periodo->nome }} <span class="font-normal text-gray-500">versione {{ $orario->versione }}</span>
                                <x-stato-orario :orario="$orario" />
                            </h2>
                            <p class="mt-0.5 text-sm text-gray-500">
                                Creato il {{ $orario->created_at?->format('d/m/Y H:i') }}@if ($orario->creatoDa) da {{ $orario->creatoDa->name }}@endif
                                · punteggio {{ $orario->punteggio }}
                            </p>
                        </div>
                    </header>

                    <div class="grid gap-x-8 gap-y-4 px-4 py-4 md:grid-cols-3">
                        <section aria-label="Consulta">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Consulta</h3>
                            <div class="grid gap-2">
                                <select data-ricerca class="js-vai-classe w-full text-sm" data-base="/orari/{{ $orario->id }}/classe" aria-label="Vista classe">
                                    <option value="">Vista classe…</option>
                                    @foreach ($classi as $classe)
                                        <option value="{{ $classe->id }}">{{ $classe->nomeCompleto() }}</option>
                                    @endforeach
                                </select>
                                <select data-ricerca class="js-vai-classe w-full text-sm" data-base="/orari/{{ $orario->id }}/docente" aria-label="Vista docente">
                                    <option value="">Vista docente…</option>
                                    @foreach ($docenti as $docente)
                                        <option value="{{ $docente->id }}">{{ $docente->nomeCompleto() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </section>

                        <section aria-label="Esporta">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Esporta in PDF</h3>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('orari.export.generale', $orario) }}" class="{{ $pulsante }}">Tabellone</a>
                                <a href="{{ route('orari.export.classi', $orario) }}" class="{{ $pulsante }}">Tutte le classi</a>
                                <a href="{{ route('orari.export.docenti', $orario) }}" class="{{ $pulsante }}">Tutti i docenti</a>
                            </div>
                        </section>

                        @if ($consentite || auth()->user()->can('gestisci-anagrafica'))
                            {{-- Ciclo di vita: i pulsanti compaiono solo per i passaggi che il ruolo può fare; duplicare crea una nuova bozza. --}}
                            <section aria-label="Stato">
                                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Stato e copia</h3>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($consentite as $nuovoStato)
                                        <form method="POST" action="{{ route('orari.stato', $orario) }}">
                                            @csrf
                                            <input type="hidden" name="stato" value="{{ $nuovoStato }}">
                                            <button type="submit" class="{{ in_array($nuovoStato, ['in_revisione', 'approvato', 'pubblicato']) ? $principale : $pulsante }}">{{ \App\Support\StatiOrario::AZIONI[$nuovoStato] }}</button>
                                        </form>
                                    @endforeach
                                    @can('gestisci-anagrafica')
                                        <form method="POST" action="{{ route('orari.duplica', $orario) }}">
                                            @csrf
                                            <button type="submit" class="{{ $pulsante }}">Duplica</button>
                                        </form>
                                    @endcan
                                </div>
                            </section>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
