<div data-riga class="flex flex-wrap items-end gap-2">
    <div>
        <label class="block text-xs text-gray-500">Codice anonimo</label>
        <input type="text" name="fabbisogni[{{ $i }}][codice_anonimo]" placeholder="es. 1B-S1" required value="{{ $riga['codice_anonimo'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs text-gray-500">Ore/sett.</label>
        <input type="number" name="fabbisogni[{{ $i }}][ore_settimanali]" min="1" max="40" required data-somma="richieste" value="{{ $riga['ore_settimanali'] ?? '' }}" class="w-20">
    </div>
    <label class="flex items-center gap-1 text-xs text-gray-600 pb-2">
        <input type="checkbox" name="fabbisogni[{{ $i }}][docente_unico]" value="1" @checked($riga['docente_unico'] ?? false)> Docente unico
    </label>
    <input type="hidden" name="fabbisogni[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
