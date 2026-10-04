@php($sede = $sede ?? null)

<div>
    <label for="nome" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="nome" id="nome" value="{{ old('nome', $sede?->nome) }}" required
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>

<div>
    <label for="indirizzo" class="block text-sm font-medium text-gray-700">Indirizzo</label>
    <input type="text" name="indirizzo" id="indirizzo" value="{{ old('indirizzo', $sede?->indirizzo) }}"
           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
</div>
