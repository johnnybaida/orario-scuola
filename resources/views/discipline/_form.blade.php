@php($disciplina = $disciplina ?? null)

<div>
    <label for="codice" class="block text-sm font-medium text-gray-700">Codice</label>
    <input type="text" name="codice" id="codice" value="{{ old('codice', $disciplina?->codice) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
</div>

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $disciplina?->nome) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
</div>

<div>
    <label for="classe_concorso" class="block text-sm font-medium text-gray-700">Classe di concorso</label>
    <input type="text" name="classe_concorso" id="classe_concorso" value="{{ old('classe_concorso', $disciplina?->classe_concorso) }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
</div>

<div>
    <label for="tipo_aula_richiesto" class="block text-sm font-medium text-gray-700">Tipo aula richiesto</label>
    <select name="tipo_aula_richiesto" id="tipo_aula_richiesto" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <option value="">— Nessuno (aula di classe) —</option>
        @foreach (['classe' => 'Classe', 'laboratorio' => 'Laboratorio', 'palestra' => 'Palestra', 'aula_musica' => 'Aula musica', 'aula_sostegno' => 'Aula sostegno', 'aula_alternativa' => 'Aula alternativa IRC'] as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(old('tipo_aula_richiesto', $disciplina?->tipo_aula_richiesto) === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="padre_id" class="block text-sm font-medium text-gray-700">Sotto-disciplina di (stesso docente)</label>
    <select name="padre_id" id="padre_id" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        <option value="">— Nessuna —</option>
        @foreach ($discipline as $d)
            <option value="{{ $d->id }}" @selected(old('padre_id', $disciplina?->padre_id) == $d->id)>{{ $d->nome }}</option>
        @endforeach
    </select>
</div>
