<div data-riga class="flex flex-wrap items-end gap-2">
    <div class="flex-1 min-w-40">
        <label class="block text-xs text-gray-500">Docente di sostegno</label>
        <select name="assegnazioni[{{ $i }}][docente_id]" required class="w-full">
            @foreach ($docentiSostegno as $docente)
                <option value="{{ $docente->id }}" @selected(($riga['docente_id'] ?? null) == $docente->id)>{{ $docente->nomeCompleto() }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">Ore/sett.</label>
        <input type="number" name="assegnazioni[{{ $i }}][ore]" min="1" max="40" required data-somma="assegnate" value="{{ $riga['ore'] ?? '' }}" class="w-20">
    </div>
    <input type="hidden" name="assegnazioni[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
