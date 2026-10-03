@php($vincolo = $vincolo ?? null)
@php($parametri = old('parametri', $vincolo?->parametri ?? []))

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
        <select name="tipo" id="tipo" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach ($etichette as $codice => $etichetta)
                <option value="{{ $codice }}" @selected(old('tipo', $vincolo?->tipo) === $codice)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="ambito_livello" class="block text-sm font-medium text-gray-700">Ambito</label>
        <select name="ambito_livello" id="ambito_livello" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach (['globale' => 'Globale', 'classe' => 'Classe', 'docente' => 'Docente'] as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(old('ambito_livello', $vincolo?->ambito_livello) === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
</div>

<div data-attiva-se="#ambito_livello=classe">
    <label class="block text-sm font-medium text-gray-700 mb-1">Classi
        <x-info testo="Per scegliere le classi imposta Ambito = Classe." />
    </label>
    <div class="flex flex-wrap gap-3">
        @foreach ($classi as $classe)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ambito_ids[]" value="{{ $classe->id }}"
                       @checked(in_array($classe->id, old('ambito_ids', $vincolo?->ambito_ids ?? [])))>
                {{ $classe->nomeCompleto() }}
            </label>
        @endforeach
    </div>
</div>

<div data-attiva-se="#ambito_livello=docente">
    <label class="block text-sm font-medium text-gray-700 mb-1">Docenti
        <x-info testo="Per scegliere i docenti imposta Ambito = Docente." />
    </label>
    <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto">
        @foreach ($docenti as $docente)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="ambito_ids[]" value="{{ $docente->id }}"
                       @checked(in_array($docente->id, old('ambito_ids', $vincolo?->ambito_ids ?? [])))>
                {{ $docente->nomeCompleto() }}
            </label>
        @endforeach
    </div>
</div>

<hr class="border-gray-100">

{{-- D1_BLOCCO_MIN_CONSECUTIVO / D3_MAX_ORE_GIORNO: disciplina comune --}}
<div data-parametri-per="D1_BLOCCO_MIN_CONSECUTIVO">
    <label class="block text-sm font-medium text-gray-700">Disciplina</label>
    <select name="parametri[disciplina_id]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($discipline as $disciplina)
            <option value="{{ $disciplina->id }}" @selected(($parametri['disciplina_id'] ?? null) == $disciplina->id)>{{ $disciplina->nome }}</option>
        @endforeach
    </select>
    <div class="grid sm:grid-cols-2 gap-4 mt-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Min ore consecutive</label>
            <input type="number" name="parametri[min_consecutive]" min="2" max="6" value="{{ $parametri['min_consecutive'] ?? 2 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">N. blocchi minimi</label>
            <input type="number" name="parametri[n_blocchi_min]" min="1" max="5" value="{{ $parametri['n_blocchi_min'] ?? 1 }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<div data-parametri-per="D3_MAX_ORE_GIORNO">
    <label class="block text-sm font-medium text-gray-700">Disciplina</label>
    <select name="parametri[disciplina_id]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($discipline as $disciplina)
            <option value="{{ $disciplina->id }}" @selected(($parametri['disciplina_id'] ?? null) == $disciplina->id)>{{ $disciplina->nome }}</option>
        @endforeach
    </select>
    <label class="block text-sm font-medium text-gray-700 mt-2">Max ore/giorno</label>
    <input type="number" name="parametri[max]" min="1" max="6" value="{{ $parametri['max'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div data-parametri-per="D6_FASCIA_ORARIA">
    <label class="block text-sm font-medium text-gray-700">Disciplina</label>
    <select name="parametri[disciplina_id]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($discipline as $disciplina)
            <option value="{{ $disciplina->id }}" @selected(($parametri['disciplina_id'] ?? null) == $disciplina->id)>{{ $disciplina->nome }}</option>
        @endforeach
    </select>
    <label class="block text-sm font-medium text-gray-700 mt-2">Tipo fascia</label>
    <select name="parametri[tipo]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="vietata" @selected(($parametri['tipo'] ?? null) === 'vietata')>Vietata</option>
        <option value="preferita" @selected(($parametri['tipo'] ?? null) === 'preferita')>Preferita</option>
    </select>
    <label class="block text-sm font-medium text-gray-700 mt-2">Slot</label>
    <div class="flex flex-wrap gap-2 max-h-40 overflow-y-auto border border-gray-200 rounded p-2">
        @foreach ($slot as $giorno => $slotGiorno)
            @foreach ($slotGiorno as $s)
                <label class="text-xs flex items-center gap-1">
                    <input type="checkbox" name="parametri[slot_ids][]" value="{{ $s->id }}"
                           @checked(in_array($s->id, $parametri['slot_ids'] ?? []))>
                    G{{ $giorno }}-{{ $s->ordine }}ª
                </label>
            @endforeach
        @endforeach
    </div>
</div>

<div data-parametri-per="T2_GIORNO_LIBERO">
    <label class="block text-sm font-medium text-gray-700">N. giorni liberi richiesti</label>
    <input type="number" name="parametri[n_giorni]" min="1" max="3" value="{{ $parametri['n_giorni'] ?? 1 }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <label class="block text-sm font-medium text-gray-700 mt-2">Giorno preferito (opzionale)</label>
    <select name="parametri[preferenze][]" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <option value="">— Nessuna preferenza —</option>
        @foreach ([1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato'] as $g => $nome)
            <option value="{{ $g }}" @selected(($parametri['preferenze'][0] ?? null) == $g)>{{ $nome }}</option>
        @endforeach
    </select>
</div>

<div data-parametri-per="T3_MAX_ORE_BUCHE">
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Max buche/giorno</label>
            <input type="number" name="parametri[max_per_giorno]" min="0" max="6" value="{{ $parametri['max_per_giorno'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Max buche/settimana</label>
            <input type="number" name="parametri[max_per_settimana]" min="0" max="20" value="{{ $parametri['max_per_settimana'] ?? '' }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>
</div>

<hr class="border-gray-100">

<div class="grid sm:grid-cols-3 gap-4">
    <div>
        <label for="severita" class="block text-sm font-medium text-gray-700">Severità</label>
        <select name="severita" id="severita" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            <option value="rigido" @selected(old('severita', $vincolo?->severita) === 'rigido')>Rigido</option>
            <option value="preferenziale" @selected(old('severita', $vincolo?->severita) === 'preferenziale')>Preferenziale</option>
        </select>
    </div>
    <div data-attiva-se="#severita=preferenziale">
        <label for="peso" class="block text-sm font-medium text-gray-700">Peso (1-100)
            <x-info testo="Il peso serve solo ai vincoli preferenziali: imposta Severità = Preferenziale." />
        </label>
        <input type="number" name="peso" id="peso" min="1" max="100" value="{{ old('peso', $vincolo?->peso) }}"
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="attivo" value="1" @checked(old('attivo', $vincolo?->attivo ?? true))> Attivo
        </label>
    </div>
</div>

<div>
    <label for="nota" class="block text-sm font-medium text-gray-700">Nota</label>
    <textarea name="nota" id="nota" rows="2" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">{{ old('nota', $vincolo?->nota) }}</textarea>
</div>
