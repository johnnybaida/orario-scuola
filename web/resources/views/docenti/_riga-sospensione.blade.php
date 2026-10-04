<div data-riga class="flex flex-wrap items-end gap-2">
    <div>
        <label class="block text-xs text-gray-500">Dal</label>
        <input type="date" name="sospensioni[{{ $i }}][dal]" required value="{{ $riga['dal'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs text-gray-500">Al (facoltativo)</label>
        <input type="date" name="sospensioni[{{ $i }}][al]" value="{{ $riga['al'] ?? '' }}">
    </div>
    <div>
        <label class="block text-xs text-gray-500">Motivo</label>
        <select name="sospensioni[{{ $i }}][motivo]" required>
            @foreach (\App\Models\Sospensione::MOTIVI as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(($riga['motivo'] ?? 'sospensione') === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex-1 min-w-40">
        <label class="block text-xs text-gray-500">Note</label>
        <input type="text" name="sospensioni[{{ $i }}][note]" maxlength="255" value="{{ $riga['note'] ?? '' }}" class="w-full">
    </div>
    <label class="flex items-center gap-1 text-xs text-gray-600 pb-2">
        <input type="checkbox" name="sospensioni[{{ $i }}][esclude_da_orario]" value="1" @checked($riga['esclude_da_orario'] ?? true)> Escludi dall'orario
        <x-info testo="Se spuntato e la sospensione è in corso, il docente non può avere cattedre: prima di generare l'orario riassegnale a un supplente. Togli la spunta per un'assenza breve che non cambia l'orario base." />
    </label>
    <input type="hidden" name="sospensioni[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
