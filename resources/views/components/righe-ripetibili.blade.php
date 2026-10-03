{{-- Righe aggiunte/rimosse via JS (resources/js/form-modifica.js); salvate con il form che le contiene.
     $partial riceve $i (indice o "__I__" nel modello) e $riga (array o null) più $dati. --}}
@props(['righe', 'partial', 'dati' => [], 'etichetta' => 'Aggiungi', 'blocca' => null])

<div data-ripetibile class="space-y-2">
    <div data-righe class="space-y-2">
        @foreach ($righe as $i => $riga)
            @include($partial, ['i' => $i, 'riga' => $riga] + $dati)
        @endforeach
    </div>
    <template data-modello>
        @include($partial, ['i' => '__I__', 'riga' => null] + $dati)
    </template>
    <div class="flex items-center gap-2">
        <button type="button" data-aggiungi @disabled($blocca) class="text-sm text-primary hover:underline cursor-pointer disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed">+ {{ $etichetta }}</button>
        @if ($blocca)
            <x-info :testo="$blocca" />
        @endif
    </div>
</div>
