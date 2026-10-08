<div data-riga class="flex flex-wrap items-end gap-2 border-b border-gray-200 pb-4 last:border-b-0 last:pb-0">
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
    <div class="basis-full">
        <label class="block text-xs text-gray-500">Note</label>
        <input type="text" name="sospensioni[{{ $i }}][note]" maxlength="255" value="{{ $riga['note'] ?? '' }}" class="w-full">
    </div>
    <div class="basis-full">
        <label class="block text-xs text-gray-500">Supplenti (facoltativo)
            <x-info testo="Spunta i docenti che sostituiscono il titolare. Dopo aver salvato, «Gestisci sostituzione» passa loro le cattedre." />
        </label>
        <div class="flex flex-wrap gap-x-4 gap-y-1 max-h-32 overflow-y-auto rounded border border-gray-200 p-2 text-sm">
            @foreach ($docentiSupplenti as $d)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="sospensioni[{{ $i }}][supplenti][]" value="{{ $d->id }}" @checked(in_array($d->id, $riga['supplenti'] ?? []))>
                    {{ $d->nomeCompleto() }}
                </label>
            @endforeach
        </div>
    </div>
    <label class="flex items-center gap-1 text-xs text-gray-600 pb-2">
        <input type="checkbox" name="sospensioni[{{ $i }}][esclude_da_orario]" value="1" @checked($riga['esclude_da_orario'] ?? true)> Escludi dall'orario
        <x-info testo="Se spuntato e la sospensione è in corso, il docente non può avere cattedre: prima di generare l'orario riassegnale a un supplente. Togli la spunta per un'assenza breve che non cambia l'orario base." />
    </label>
    <input type="hidden" name="sospensioni[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <span class="pb-2 text-sm">
        @if (! empty($riga['id']) && auth()->user()->can('gestisci-anagrafica'))
            <a href="{{ route('sostituzioni.form', $riga['id']) }}" class="text-primary hover:underline">Gestisci sostituzione</a>
        @else
            <span class="text-gray-400">Gestisci sostituzione</span>
            <x-info testo="Si attiva dopo aver salvato la sospensione e solo per amministratore e referente orario." />
        @endif
    </span>
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
</div>
