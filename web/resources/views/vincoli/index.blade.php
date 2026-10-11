@extends('layouts.app')

@section('titolo', 'Vincoli')

@section('contenuto')
    <h1 class="text-xl font-semibold mb-6">Vincoli</h1>

    <x-guida>
        Regole aggiuntive per la generazione automatica dell'orario, oltre a quelle di sistema sempre attive
        (es. un docente non può essere in due posti contemporaneamente). Un vincolo <strong>rigido</strong> deve
        essere rispettato sempre, altrimenti la generazione non trova nessun orario; uno <strong>preferenziale</strong> ha un peso (1-100) e
        viene violato solo se non c'è alternativa migliore.
    </x-guida>

    <x-copia-da-sede area="vincoli" />

    <x-barra-tabella>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            @php $classiSelect = 'rounded border-gray-300 shadow-sm text-sm focus:border-primary focus:ring-primary'; @endphp
            <select name="tipo" aria-label="Tipo" class="{{ $classiSelect }}" onchange="this.form.submit()">
                <option value="">Tutti i tipi</option>
                @foreach ($etichette as $tipo => $etichetta)
                    <option value="{{ $tipo }}" @selected($filtroTipo === $tipo)>{{ $etichetta }}</option>
                @endforeach
            </select>
            <select name="docente" aria-label="Docente" class="{{ $classiSelect }}" onchange="this.form.submit()">
                <option value="">Tutti i docenti</option>
                @foreach ($docentiElenco as $d)
                    <option value="{{ $d->id }}" @selected(($filtri['docente'] ?? '') == $d->id)>{{ $d->nomeCompleto() }}</option>
                @endforeach
            </select>
            <select name="classe" aria-label="Classe" class="{{ $classiSelect }}" onchange="this.form.submit()">
                <option value="">Tutte le classi</option>
                @foreach ($classiElenco as $c)
                    <option value="{{ $c->id }}" @selected(($filtri['classe'] ?? '') == $c->id)>{{ $c->nomeCompleto() }}</option>
                @endforeach
            </select>
            <select name="disciplina" aria-label="Disciplina" class="{{ $classiSelect }}" onchange="this.form.submit()">
                <option value="">Tutte le discipline</option>
                @foreach ($disciplineElenco as $d)
                    <option value="{{ $d->id }}" @selected(($filtri['disciplina'] ?? '') == $d->id)>{{ $d->nome }}</option>
                @endforeach
            </select>
            <select name="severita" aria-label="Severità" class="{{ $classiSelect }}" onchange="this.form.submit()">
                <option value="">Ogni severità</option>
                <option value="rigido" @selected(($filtri['severita'] ?? '') === 'rigido')>Rigido</option>
                <option value="preferenziale" @selected(($filtri['severita'] ?? '') === 'preferenziale')>Preferenziale</option>
            </select>
            @if (array_filter($filtri))
                <a href="{{ route('vincoli.index') }}" class="text-sm text-gray-600 underline">Azzera i filtri</a>
            @endif
        </form>
        <x-slot:azioni>
            @can('gestisci-anagrafica')
                <a data-modale href="{{ route('vincoli.create') }}" class="bg-primary text-white rounded px-4 py-2 text-sm hover:bg-primary/90 transition-colors cursor-pointer">Nuovo vincolo</a>
            @endcan
        </x-slot:azioni>
    </x-barra-tabella>

    <x-barra-selezione tabella="vincoli" />

    @can('gestisci-anagrafica')
        <label class="mb-3 inline-flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" class="js-sel-tutti"> Seleziona tutti</label>
    @endcan

    @forelse ($gruppi as $tipo => $vincoli)
        <section class="mb-8">
            <h2 class="mb-3 text-base font-semibold text-gray-800">{{ $etichette[$tipo] ?? $tipo }} <span class="text-sm font-normal text-gray-500">({{ $vincoli->count() }})</span></h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($vincoli as $vincolo)
                    @php
                        $livello = $vincolo->ambito_livello;
                        $elenco = collect($vincolo->ambito_ids ?? [])->map(fn ($id) => $nomi[$livello][$id] ?? null)->filter()->values();
                        $ambito = match (true) {
                            $livello === 'globale' => 'Tutta la sede',
                            $elenco->isEmpty() => ucfirst($livello).': nessuno scelto',
                            default => ($livello === 'docente' ? 'Docenti' : 'Classi').': '.$elenco->take(3)->implode(', ').($elenco->count() > 3 ? ' (+'.($elenco->count() - 3).')' : ''),
                        };
                        $nomiDiscipline = in_array($vincolo->tipo, ['D1_BLOCCO_MIN_CONSECUTIVO', 'D3_MAX_ORE_GIORNO', 'D6_FASCIA_ORARIA', 'D12_BLOCCO_MAX_CONSECUTIVO', 'D13_DISCIPLINA_SEGUITA'], true)
                            ? (collect(\App\Constraints\DisciplineVincolo::ids($vincolo->parametri ?? []))->map(fn ($id) => $discipline[$id] ?? '?')->implode(', ') ?: 'Tutte')
                            : null;
                        try { $descrizione = \App\Constraints\Catalogo::istanza($vincolo->tipo)->descrizione($vincolo->parametri ?? []); } catch (\Throwable) { $descrizione = ''; }
                    @endphp
                    <article data-vincolo class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 text-sm {{ $vincolo->attivo ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                @can('gestisci-anagrafica')<input type="checkbox" class="js-sel" value="{{ route('vincoli.destroy', $vincolo) }}" aria-label="Seleziona">@endcan
                                <span class="rounded px-2 py-0.5 text-xs font-medium {{ $vincolo->severita === 'rigido' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $vincolo->severita === 'rigido' ? 'Rigido' : 'Preferenziale · peso '.$vincolo->peso }}
                                </span>
                            </div>
                            @can('gestisci-anagrafica')
                                <label class="relative inline-flex cursor-pointer items-center" title="Attivo">
                                    <input type="checkbox" class="peer sr-only js-vincolo-attivo" data-url="{{ route('vincoli.attiva', $vincolo) }}" @checked($vincolo->attivo) aria-label="Vincolo attivo">
                                    <span class="h-5 w-9 rounded-full bg-gray-300 transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span>
                                </label>
                            @else
                                <span class="text-xs text-gray-500">{{ $vincolo->attivo ? 'Attivo' : 'Disattivato' }}</span>
                            @endcan
                        </div>
                        @if ($descrizione)<p class="text-gray-800">{{ $descrizione }}</p>@endif
                        <dl class="space-y-0.5 text-gray-600">
                            <div title="{{ $elenco->implode(', ') }}"><dt class="inline text-gray-400">Ambito: </dt><dd class="inline">{{ $ambito }}</dd></div>
                            @if ($nomiDiscipline !== null)<div><dt class="inline text-gray-400">Discipline: </dt><dd class="inline">{{ $nomiDiscipline }}</dd></div>@endif
                            @if ($vincolo->nota)<div><dt class="inline text-gray-400">Nota: </dt><dd class="inline">{{ $vincolo->nota }}</dd></div>@endif
                        </dl>
                        @can('gestisci-anagrafica')
                            <div class="mt-auto pt-1 text-right"><a data-modale href="{{ route('vincoli.edit', $vincolo) }}" class="text-gray-600 hover:text-gray-900 underline">Modifica</a></div>
                        @endcan
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <p class="text-sm text-gray-500">{{ array_filter($filtri) ? 'Nessun vincolo corrisponde ai filtri.' : 'Nessun vincolo.' }}</p>
    @endforelse
@endsection
