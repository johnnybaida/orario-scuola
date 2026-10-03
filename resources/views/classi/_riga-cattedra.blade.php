<div data-riga class="flex flex-wrap items-end gap-2">
    <div class="flex-1 min-w-40">
        <label class="block text-xs text-gray-500">Disciplina</label>
        <select name="cattedre[{{ $i }}][disciplina_id]" required class="w-full">
            @foreach ($discipline as $disciplina)
                <option value="{{ $disciplina->id }}" @selected(($riga['disciplina_id'] ?? null) == $disciplina->id)>{{ $disciplina->nome }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-40">
        <label class="block text-xs text-gray-500">Docente</label>
        <select name="cattedre[{{ $i }}][docente_id]" required class="w-full">
            @foreach ($docenti as $docente)
                <option value="{{ $docente->id }}" @selected(($riga['docente_id'] ?? null) == $docente->id)>{{ $docente->nomeCompleto() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">Ore</label>
        <input type="number" name="cattedre[{{ $i }}][ore]" min="1" max="20" required data-somma="cattedre" value="{{ $riga['ore'] ?? '' }}" class="w-20">
    </div>
    <label class="flex items-center gap-1 text-xs text-gray-600 pb-2">
        <input type="checkbox" name="cattedre[{{ $i }}][compresenza]" value="1" @checked($riga['compresenza'] ?? false)> Compresenza
    </label>
    <input type="hidden" name="cattedre[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
