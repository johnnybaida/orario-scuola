<div data-riga class="flex flex-wrap items-end gap-2">
    <div>
        <label class="block text-xs text-gray-500">Giorno</label>
        <select name="assistenze[{{ $i }}][giorno]" required>
            @foreach (\App\Models\Slot::GIORNI as $numero => $nome)
                <option value="{{ $numero }}" @selected(($riga['giorno'] ?? null) == $numero)>{{ $nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs text-gray-500">Pausa</label>
        <select name="assistenze[{{ $i }}][ordine]" required data-somma-minuti="cattedre">
            @foreach ($pause as $ordine => $pausa)
                <option value="{{ $ordine }}" data-minuti="{{ $pausa['conteggio'] }}" @selected(($riga['ordine'] ?? null) == $ordine)>{{ $pausa['etichetta'] }}</option>
            @endforeach
        </select>
    </div>
    <input type="hidden" name="assistenze[{{ $i }}][id]" value="{{ $riga['id'] ?? '' }}">
    <button type="button" data-rimuovi class="text-red-600 hover:text-red-800 underline text-sm pb-2 cursor-pointer">Rimuovi</button>
    @if (! empty($riga['avviso']))
        <span class="basis-full text-xs text-amber-700">⚠ {{ $riga['avviso'] }}</span>
    @endif
</div>
