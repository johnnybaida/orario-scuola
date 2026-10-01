@php($aula = $aula ?? null)

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
    <input type="text" name="tipo" id="tipo" list="tipi-aula" value="{{ old('tipo', $aula?->tipo ?? 'classe') }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
    <datalist id="tipi-aula">
        @foreach ($tipiSuggeriti as $tipo)
            <option value="{{ $tipo }}">
        @endforeach
    </datalist>
    <p class="mt-1 text-xs text-gray-500">
        Scegli un tipo suggerito o scrivine uno nuovo (es. per un'aula dedicata a una disciplina in modalità DADA).
    </p>
</div>

<div>
    <label for="capienza" class="block text-sm font-medium text-gray-700">Capienza</label>
    <input type="number" name="capienza" id="capienza" min="1" value="{{ old('capienza', $aula?->capienza ?? 1) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>
