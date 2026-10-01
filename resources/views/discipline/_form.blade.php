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
    <label for="tipo_aula_richiesto" class="block text-sm font-medium text-gray-700">Tipo aula richiesto</label>
    <input type="text" name="tipo_aula_richiesto" id="tipo_aula_richiesto" list="tipi-aula"
           value="{{ old('tipo_aula_richiesto', $disciplina?->tipo_aula_richiesto) }}"
           placeholder="vuoto = aula di classe"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <datalist id="tipi-aula">
        @foreach ($tipiAula as $tipo)
            <option value="{{ $tipo }}">
        @endforeach
    </datalist>
    <p class="mt-1 text-xs text-gray-500">
        Deve coincidere con il tipo di un'aula censita. Per DADA, crea un'aula dedicata con un tipo a piacere
        (es. "dada_italiano") e usalo qui.
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
