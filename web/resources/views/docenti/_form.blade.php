@php($docente = $docente ?? null)

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
        <input type="text" name="nome" id="nome" value="{{ old('nome', $docente?->nome) }}" required
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
    <div>
        <label for="cognome" class="block text-sm font-medium text-gray-700">Cognome</label>
        <input type="text" name="cognome" id="cognome" value="{{ old('cognome', $docente?->cognome) }}" required
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
</div>

<div>
    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
    <input type="email" name="email" id="email" value="{{ old('email', $docente?->email) }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div class="grid sm:grid-cols-3 gap-4">
    <div>
        <label for="tipo_contratto" class="block text-sm font-medium text-gray-700">Contratto</label>
        <select name="tipo_contratto" id="tipo_contratto" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach (['tempo_indeterminato' => 'Tempo indeterminato', 'tempo_determinato_annuale' => 'Determinato annuale', 'tempo_determinato_fino_termine' => 'Determinato fino al termine', 'supplenza_breve' => 'Supplenza breve'] as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(old('tipo_contratto', $docente?->tipo_contratto) === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="tipo_posto" class="block text-sm font-medium text-gray-700">Tipo posto</label>
        <select name="tipo_posto" id="tipo_posto" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach (['comune' => 'Comune', 'sostegno' => 'Sostegno', 'potenziamento' => 'Potenziamento', 'irc' => 'IRC', 'strumento' => 'Strumento musicale'] as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(old('tipo_posto', $docente?->tipo_posto) === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="regime" class="block text-sm font-medium text-gray-700">Regime</label>
        <select name="regime" id="regime" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach (['tempo_pieno' => 'Tempo pieno', 'part_time_orizzontale' => 'Part-time orizzontale', 'part_time_verticale' => 'Part-time verticale', 'part_time_misto' => 'Part-time misto'] as $valore => $etichetta)
                <option value="{{ $valore }}" @selected(old('regime', $docente?->regime) === $valore)>{{ $etichetta }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="ore_dovute" class="block text-sm font-medium text-gray-700">Ore dovute (18 = cattedra intera)</label>
        <input type="number" name="ore_dovute" id="ore_dovute" min="1" max="24" value="{{ old('ore_dovute', $docente?->ore_dovute ?? 18) }}" required
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
    <div class="flex items-end pb-2">
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="coe" value="1" @checked(old('coe', $docente?->coe))> Cattedra Orario Esterna (COE)
        </label>
    </div>
</div>

<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Classi di concorso abilitanti</label>
    @php($scelte = old('classi_concorso', $docente?->relationLoaded('classiConcorso') ? $docente->classiConcorso->pluck('classe_concorso')->all() : []))
    <p class="text-xs text-gray-500 mb-2">Il codice dell'abilitazione, con accanto le discipline che permette di insegnare.</p>
    <div class="flex flex-wrap gap-x-8 gap-y-3">
        @forelse (collect(array_keys($classiConcorso))->merge($scelte)->unique()->sort() as $cc)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="classi_concorso[]" value="{{ $cc }}" @checked(in_array($cc, $scelte))>
                <span><strong>{{ $cc }}</strong>@if (! empty($classiConcorso[$cc])) <span class="text-gray-500">— {{ implode(', ', $classiConcorso[$cc]) }}</span>@endif</span>
            </label>
        @empty
            <p class="text-xs text-gray-500">Nessuna classe di concorso censita: indicale nelle discipline.</p>
        @endforelse
    </div>
</div>

