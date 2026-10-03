@php($classe = $classe ?? null)

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="anno_corso" class="block text-sm font-medium text-gray-700">Anno di corso</label>
        <select name="anno_corso" id="anno_corso" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach ([1, 2, 3] as $anno)
                <option value="{{ $anno }}" @selected(old('anno_corso', $classe?->anno_corso) == $anno)>{{ $anno }}ª</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="sezione" class="block text-sm font-medium text-gray-700">Sezione</label>
        <input type="text" name="sezione" id="sezione" value="{{ old('sezione', $classe?->sezione) }}" required maxlength="10"
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="sede_id" class="block text-sm font-medium text-gray-700">Sede</label>
        <select name="sede_id" id="sede_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach ($sedi as $sede)
                <option value="{{ $sede->id }}" @selected(old('sede_id', $classe?->sede_id) == $sede->id)>{{ $sede->nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="aula_base_id" class="block text-sm font-medium text-gray-700">Aula base</label>
        <select name="aula_base_id" id="aula_base_id" class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            <option value="">— Nessuna —</option>
            @foreach ($aule as $aula)
                <option value="{{ $aula->id }}" @selected(old('aula_base_id', $classe?->aula_base_id) == $aula->id)>{{ $aula->nome }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Vuota se la scuola usa DADA: gli alunni si spostano nelle aule delle discipline.</p>
    </div>
</div>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label for="quadro_orario_id" class="block text-sm font-medium text-gray-700">Quadro orario</label>
        <select name="quadro_orario_id" id="quadro_orario_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            @foreach ($quadri as $quadro)
                <option value="{{ $quadro->id }}" @selected(old('quadro_orario_id', $classe?->quadro_orario_id) == $quadro->id)>{{ $quadro->nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="tempo_scuola" class="block text-sm font-medium text-gray-700">Tempo scuola</label>
        <select name="tempo_scuola" id="tempo_scuola" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            <option value="normale" @selected(old('tempo_scuola', $classe?->tempo_scuola) === 'normale')>Normale</option>
            <option value="prolungato" @selected(old('tempo_scuola', $classe?->tempo_scuola) === 'prolungato')>Prolungato</option>
        </select>
    </div>
</div>

<div data-attiva-se="#tempo_scuola=prolungato">
    <span class="block text-sm font-medium text-gray-700 mb-1">Rientri pomeridiani
        <x-info testo="I rientri valgono per il tempo prolungato: imposta Tempo scuola = Prolungato." />
    </span>
    @php($rientri = old('rientri', $rientriAttivi ?? []))
    <div class="flex flex-wrap gap-4">
        @forelse ($giorniRientro as $giorno)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="rientri[]" data-rientro value="{{ $giorno }}" @checked(in_array($giorno, $rientri))>
                {{ \App\Models\Slot::GIORNI[$giorno] }}
            </label>
        @empty
            <p class="text-xs text-gray-500">La scansione oraria di istituto non prevede ore pomeridiane.</p>
        @endforelse
    </div>
    <p class="mt-1 text-xs text-gray-500">Giorni di rientro della classe (tempo prolungato): ognuno si sceglie in modo indipendente. Il dettaglio delle singole ore resta negli "Slot attivi".</p>
</div>

<div>
    <label for="n_alunni" class="block text-sm font-medium text-gray-700">Numero alunni</label>
    <input type="number" name="n_alunni" id="n_alunni" min="0" max="35" value="{{ old('n_alunni', $classe?->n_alunni ?? 0) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>
