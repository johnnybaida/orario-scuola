{{-- Slot di un vincolo: una riga per giorno con le sue ore; «Tutte» spunta l'intero giorno. --}}
<label class="block text-sm font-medium text-gray-700 mt-2">{{ $etichettaSlot ?? 'Slot' }} <span class="text-destructive" aria-hidden="true">*</span></label>
{{-- Una riga per giorno: il nome del giorno e le sue ore; «Tutte» spunta l'intero giorno. --}}
<div class="space-y-1 rounded border border-gray-200 p-2" data-slot-giorni>
    @foreach ($slot as $giorno => $slotGiorno)
        <div class="flex items-center gap-3 text-xs">
            <span class="w-20 shrink-0 font-medium text-gray-700">{{ \App\Models\Slot::GIORNI[$giorno] ?? "Giorno {$giorno}" }}</span>
            @foreach ($slotGiorno as $s)
                <label class="flex items-center gap-1">
                    <input type="checkbox" name="parametri[slot_ids][]" value="{{ $s->id }}" @checked(in_array($s->id, $parametri['slot_ids'] ?? []))>
                    {{ $s->ordine }}ª
                </label>
            @endforeach
            <button type="button" data-giorno-tutto class="ml-auto text-gray-500 underline cursor-pointer">Tutte</button>
        </div>
    @endforeach
</div>
