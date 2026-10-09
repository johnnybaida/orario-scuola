@php($disciplina = $disciplina ?? null)

<div>
    <label for="codice" class="block text-sm font-medium text-gray-700">Codice</label>
    <input type="text" name="codice" id="codice" value="{{ old('codice', $disciplina?->codice) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <p class="mt-1 text-xs text-gray-500">
        Sigla libera e unica. Per una seconda lingua straniera usa <strong>FRA</strong>, <strong>SPA</strong> o <strong>TED</strong>
        (oppure scrivi «seconda lingua» nel nome): così l'aula DADA delle lingue è riconosciuta.
    </p>
</div>

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $disciplina?->nome) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="classe_concorso" class="block text-sm font-medium text-gray-700">Classe di concorso</label>
    <input type="text" name="classe_concorso" id="classe_concorso" value="{{ old('classe_concorso', $disciplina?->classe_concorso) }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="tipo_aula_richiesto" class="block text-sm font-medium text-gray-700">Aula richiesta</label>
    @php($sel = old('tipo_aula_richiesto', $disciplina?->tipo_aula_richiesto))
    <select name="tipo_aula_richiesto" id="tipo_aula_richiesto" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="">Aula della classe (nessuna aula speciale)</option>
        @foreach ($tipiAula as $tipo => $etichetta)
            <option value="{{ $tipo }}" @selected($sel === $tipo)>{{ $etichetta }}</option>
        @endforeach
        @if ($sel && ! isset($tipiAula[$sel]))
            <option value="{{ $sel }}" selected>{{ $sel }} (nessuna aula censita)</option>
        @endif
    </select>
    <p class="mt-1 text-xs text-gray-500">
        Elenco dei tipi di aula censiti in "Aule". Per DADA: crea l'aula e spunta questa disciplina, il collegamento avviene da solo;
        qui puoi comunque sceglierlo a mano.
    </p>
    @if (count($tipiAula) > 1)
        @php($extraSel = collect(old('tipi_aula_extra', $disciplina?->tipi_aula_extra ?? [])))
        <label class="mt-3 block text-sm font-medium text-gray-700">Altre aule ammesse
            <x-info testo="Facoltativo: tipi di aula in cui la disciplina può svolgersi in più rispetto a quello richiesto. Serve, per esempio, quando ha la propria aula DADA e anche un'aula DADA condivisa con altre discipline: il generatore sceglie quella libera." />
        </label>
        <div class="mt-1 flex max-h-32 flex-wrap gap-x-4 gap-y-1 overflow-y-auto rounded border border-gray-200 p-2 text-sm">
            @foreach ($tipiAula as $tipo => $etichetta)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="tipi_aula_extra[]" value="{{ $tipo }}" @checked($extraSel->contains($tipo))> {{ $etichetta }}
                </label>
            @endforeach
        </div>
    @endif
</div>

<div>
    <label for="padre_id" class="block text-sm font-medium text-gray-700">Sotto-disciplina di (stesso docente)</label>
    <select name="padre_id" id="padre_id" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="">— Nessuna —</option>
        @foreach ($discipline as $d)
            <option value="{{ $d->id }}" @selected(old('padre_id', $disciplina?->padre_id) == $d->id)>{{ $d->nome }}</option>
        @endforeach
    </select>
</div>
