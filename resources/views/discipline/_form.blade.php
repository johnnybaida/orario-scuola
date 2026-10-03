@php($disciplina = $disciplina ?? null)

<div>
    <label for="codice" class="block text-sm font-medium text-gray-700">Codice</label>
    <input type="text" name="codice" id="codice" value="{{ old('codice', $disciplina?->codice) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
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
        Elenco dei tipi di aula censiti in "Sedi/Aule". Per DADA: crea l'aula con tipo "DADA · questa disciplina"
        e il collegamento avviene da solo; qui puoi comunque sceglierlo a mano.
    </p>
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
