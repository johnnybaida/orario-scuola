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
    <div class="min-w-48">
        <label class="block text-xs text-gray-500">Supplenti (facoltativo)
            <x-info testo="Tieni premuto Ctrl (Cmd su Mac) per sceglierne più d'uno. Dopo aver salvato, «Gestisci sostituzione» passa le cattedre del titolare ai supplenti scelti." />
        </label>
        <select name="sospensioni[{{ $i }}][supplenti][]" multiple size="3" class="w-full">
            @foreach ($docentiSupplenti as $d)
                <option value="{{ $d->id }}" @selected(in_array($d->id, $riga['supplenti'] ?? []))>{{ $d->nomeCompleto() }}</option>
            @endforeach
        </select>
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
