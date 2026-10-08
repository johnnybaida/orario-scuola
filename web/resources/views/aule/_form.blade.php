@php($aula = $aula ?? null)
@php($dada = $aula && \App\Enums\TipoAula::eDada($aula->tipo) ? $discipline->firstWhere('tipo_aula_richiesto', $aula->tipo) : null)
@php($tipoSel = old('tipo', $dada ? 'dada:'.$dada->id : ($aula?->tipo ?? 'classe')))

<div>
    <label for="sede_id" class="block text-sm font-medium text-gray-700">Sede</label>
    <select name="sede_id" id="sede_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
        @foreach ($sedi as $sede)
            <option value="{{ $sede->id }}" @selected(old('sede_id', $aula?->sede_id) == $sede->id)>{{ $sede->nome }}</option>
        @endforeach
    </select>
</div>

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
        <optgroup label="Aula DADA (dedicata a una disciplina)">
            @foreach ($discipline as $disciplina)
                <option value="dada:{{ $disciplina->id }}" @selected($tipoSel === 'dada:'.$disciplina->id)>DADA · {{ $disciplina->nome }}</option>
            @endforeach
        </optgroup>
    </select>
    <p class="mt-1 text-xs text-gray-500">
        Per la didattica DADA scegli "DADA · <em>disciplina</em>": gli alunni si spostano in quest'aula per quella
        materia e la disciplina viene collegata automaticamente.
    </p>
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
