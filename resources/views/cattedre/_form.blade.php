@php($cattedra = $cattedra ?? null)

<div>
    <label for="classe_id" class="block text-sm font-medium text-gray-700">Classe</label>
    <select name="classe_id" id="classe_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        @foreach ($classi as $classe)
            <option value="{{ $classe->id }}" @selected(old('classe_id', $cattedra?->classe_id) == $classe->id)>{{ $classe->nomeCompleto() }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="disciplina_id" class="block text-sm font-medium text-gray-700">Disciplina</label>
    <select name="disciplina_id" id="disciplina_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        @foreach ($discipline as $disciplina)
            <option value="{{ $disciplina->id }}" @selected(old('disciplina_id', $cattedra?->disciplina_id) == $disciplina->id)>{{ $disciplina->nome }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="docente_id" class="block text-sm font-medium text-gray-700">Docente</label>
    <select name="docente_id" id="docente_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        @foreach ($docenti as $docente)
            <option value="{{ $docente->id }}" @selected(old('docente_id', $cattedra?->docente_id) == $docente->id)>{{ $docente->nomeCompleto() }}</option>
        @endforeach
    </select>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="ore" class="block text-sm font-medium text-gray-700">Ore settimanali</label>
        <input type="number" name="ore" id="ore" min="1" max="20" value="{{ old('ore', $cattedra?->ore) }}" required
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="compresenza" value="1" @checked(old('compresenza', $cattedra?->compresenza))> Compresenza
        </label>
    </div>
</div>
