<div data-riga class="flex flex-wrap items-end gap-2">
    <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500">Disciplina</label>
        <select name="righe[{{ $i }}][disciplina_id]" required class="w-full">
            @foreach ($discipline as $disciplina)
                <option value="{{ $disciplina->id }}" @selected(($riga['disciplina_id'] ?? null) == $disciplina->id)>{{ $disciplina->nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">Ore/sett.</label>
        <input type="number" name="righe[{{ $i }}][ore_settimanali]" min="1" max="40" required data-somma="quadro"
               value="{{ $riga['ore_settimanali'] ?? '' }}" class="w-20">
    </div>
    <input type="hidden" name="righe[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
