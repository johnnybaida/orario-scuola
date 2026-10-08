{{-- Elenco di tabelle da spuntare (esporta e importa dati): $tabelle = nome => [etichetta, righe]. --}}
@props(['tabelle', 'righe' => null])
<div class="flex gap-3 mb-2 text-sm">
    <button type="button" data-seleziona="tutte" class="underline text-gray-600 cursor-pointer">Seleziona tutte</button>
    <button type="button" data-seleziona="nessuna" class="underline text-gray-600 cursor-pointer">Nessuna</button>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1 text-sm">
    @foreach ($tabelle as $nome => $t)
        <label class="flex items-center gap-2">
            <input type="checkbox" name="tabelle[]" value="{{ $nome }}" checked>
            {{ $t['etichetta'] }} <span class="text-gray-400">({{ $righe[$nome] ?? $t['righe'] }})</span>
        </label>
    @endforeach
</div>
