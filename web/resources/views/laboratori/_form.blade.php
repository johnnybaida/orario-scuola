@php($laboratorio = $laboratorio ?? null)
@php($docentiSel = collect(old('docenti', $laboratorio?->docenti->pluck('id')->all() ?? [])))
@php($classiSel = collect(old('classi', $laboratorio?->classi->pluck('id')->all() ?? [])))
@php($slotSel = collect(old('slot_ids', $laboratorio?->slot->pluck('id')->all() ?? [])))

<div data-laboratorio data-url="{{ route('laboratori.disponibilita') }}" data-escludi="{{ $laboratorio?->id }}" class="contents">
    <div>
        <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
        <input type="text" name="nome" id="nome" value="{{ old('nome', $laboratorio?->nome) }}" required maxlength="255" placeholder="Es. Latino"
               class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    </div>

    <div>
        <label for="aula_id" class="block text-sm font-medium text-gray-700">Aula</label>
        <select name="aula_id" id="aula_id" data-campo-aula class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
            <option value="">— Nessuna —</option>
            @foreach ($aule as $aula)
                <option value="{{ $aula->id }}" @selected(old('aula_id', $laboratorio?->aula_id) == $aula->id)>{{ $aula->nome }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Docenti
            <x-info testo="Uno o più docenti che tengono il laboratorio: saranno occupati nelle ore scelte." />
        </label>
        <div class="flex flex-wrap gap-x-4 gap-y-1 max-h-32 overflow-y-auto rounded border border-gray-200 p-2 text-sm">
            @foreach ($docenti as $docente)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="docenti[]" value="{{ $docente->id }}" data-campo-docente @checked($docentiSel->contains($docente->id))>
                    {{ $docente->nomeCompleto() }}
                </label>
            @endforeach
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Quando (ore del pomeriggio)
            <x-info testo="Le ore in giallo non sono libere (docente o aula occupati): passa il mouse per il motivo. Il calcolo usa l'orario pubblicato, o l'ultimo se non ce n'è uno pubblicato." />
        </label>
        <div class="space-y-1 text-sm">
            @forelse ($slotPerGiorno as $giorno => $slotGiorno)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-20 text-gray-500">{{ \App\Models\Slot::GIORNI[$giorno] ?? $giorno }}</span>
                    @foreach ($slotGiorno as $s)
                        <label class="flex items-center gap-1.5 rounded px-1.5" data-slot-etichetta="{{ $s->id }}">
                            <input type="checkbox" name="slot_ids[]" value="{{ $s->id }}" @checked($slotSel->contains($s->id))>
                            {{ $s->ordine }}ª <span class="text-gray-400">{{ substr($s->inizio, 0, 5) }}–{{ substr($s->fine, 0, 5) }}</span>
                        </label>
                    @endforeach
                </div>
            @empty
                <p class="text-gray-500">Nessuna ora pomeridiana nella scansione oraria.</p>
            @endforelse
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Classi destinatarie <span class="text-xs text-gray-400">(facoltativo, informativo)</span></label>
        <div class="flex flex-wrap gap-x-4 gap-y-1 max-h-24 overflow-y-auto rounded border border-gray-200 p-2 text-sm">
            @foreach ($classi as $classe)
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="classi[]" value="{{ $classe->id }}" @checked($classiSel->contains($classe->id))> {{ $classe->nomeCompleto() }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label for="n_partecipanti" class="block text-sm font-medium text-gray-700">Partecipanti</label>
            <input type="number" name="n_partecipanti" id="n_partecipanti" min="1" max="500" value="{{ old('n_partecipanti', $laboratorio?->n_partecipanti) }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label for="note" class="block text-sm font-medium text-gray-700">Note</label>
            <input type="text" name="note" id="note" maxlength="255" value="{{ old('note', $laboratorio?->note) }}"
                   class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="hidden" name="attivo" value="0">
        <input type="checkbox" name="attivo" value="1" @checked(old('attivo', $laboratorio?->attivo ?? true))> Attivo
        <x-info testo="Se tolto, il laboratorio resta salvato ma non occupa docenti e aule." />
    </label>
</div>
