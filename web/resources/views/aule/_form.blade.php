@php($aula = $aula ?? null)
@php($eDada = $aula && \App\Enums\TipoAula::eDada($aula->tipo))
@php($tipoSel = old('tipo', $eDada ? 'dada' : ($aula?->tipo ?? 'classe')))
@php($dadaSel = collect(old('dada_discipline', $eDada ? $discipline->filter(fn ($d) => $d->accettaTipo($aula->tipo))->pluck('id')->all() : [])))

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $aula?->nome) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
    <select name="tipo" id="tipo" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        <optgroup label="Aule comuni">
            @foreach ($tipiSuggeriti as $tipo)
                <option value="{{ $tipo }}" @selected($tipoSel === $tipo)>{{ \App\Enums\TipoAula::etichettaDi($tipo) }}</option>
            @endforeach
        </optgroup>
        <optgroup label="Didattica DADA">
            <option value="dada" @selected($tipoSel === 'dada')>DADA · aula dedicata a una o più discipline</option>
        </optgroup>
    </select>
    <p class="mt-1 text-xs text-gray-500">
        Per la didattica DADA scegli «DADA» e spunta qui sotto le discipline che si svolgono in quest'aula.
    </p>
</div>

<div data-attiva-se="#tipo=dada">
    <label class="block text-sm font-medium text-gray-700">Discipline di quest'aula DADA
        <x-info testo="Si attiva scegliendo il tipo «DADA». Gli alunni si spostano in quest'aula per le discipline spuntate: una sola per un'aula dedicata, più d'una se l'aula è condivisa (per esempio Italiano, Inglese e Spagnolo). Ogni disciplina può avere più aule: la propria e una condivisa. Le discipline spuntate non possono usare l'aula nello stesso momento (capienza 1)." />
    </label>
    <div class="mt-1 flex max-h-40 flex-wrap gap-x-4 gap-y-1 overflow-y-auto rounded border border-gray-200 p-2 text-sm">
        @foreach ($discipline as $disciplina)
            <label class="flex items-center gap-1.5">
                <input type="checkbox" name="dada_discipline[]" value="{{ $disciplina->id }}" @checked($dadaSel->contains($disciplina->id))> {{ $disciplina->nome }}
            </label>
        @endforeach
    </div>
</div>

<div>
    <label for="piano" class="block text-sm font-medium text-gray-700">Piano</label>
    <input type="number" name="piano" id="piano" min="-3" max="10" value="{{ old('piano', $aula?->piano) }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <p class="mt-1 text-xs text-gray-500">0 = piano terra, negativo = interrato. Facoltativo: serve al vincolo «Spostamenti tra piani».</p>
</div>

<div>
    <label for="capienza" class="block text-sm font-medium text-gray-700">Capienza (lezioni contemporanee)</label>
    <input type="number" name="capienza" id="capienza" min="1" value="{{ old('capienza', $aula?->capienza ?? 1) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <p class="mt-1 text-xs text-gray-500">1 = una sola classe alla volta. Per DADA di solito 1 per ogni aula della disciplina.</p>
</div>
