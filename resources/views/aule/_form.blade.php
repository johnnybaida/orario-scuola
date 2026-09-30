@php($aula = $aula ?? null)

<div>
    <label for="sede_id" class="block text-sm font-medium text-gray-700">Sede</label>
    <select name="sede_id" id="sede_id" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        @foreach ($sedi as $sede)
            <option value="{{ $sede->id }}" @selected(old('sede_id', $aula?->sede_id) == $sede->id)>{{ $sede->nome }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $aula?->nome) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
</div>

<div>
    <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
    <select name="tipo" id="tipo" required class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
        @foreach (['classe' => 'Classe', 'laboratorio' => 'Laboratorio', 'palestra' => 'Palestra', 'aula_musica' => 'Aula musica', 'aula_sostegno' => 'Aula sostegno', 'aula_alternativa' => 'Aula alternativa IRC'] as $valore => $etichetta)
            <option value="{{ $valore }}" @selected(old('tipo', $aula?->tipo) === $valore)>{{ $etichetta }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="capienza" class="block text-sm font-medium text-gray-700">Capienza</label>
    <input type="number" name="capienza" id="capienza" min="1" value="{{ old('capienza', $aula?->capienza ?? 1) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-gray-500 focus:ring-gray-500">
</div>
