{{-- Righe aggiunte/rimosse via JS (resources/js/form-modifica.js); salvate con il form che le contiene.
     $partial riceve $i (indice o "__I__" nel modello) e $riga (array o null) più $dati. --}}
@props(['righe', 'partial', 'dati' => [], 'etichetta' => 'Aggiungi'])

<div data-ripetibile class="space-y-2">
    <div data-righe class="space-y-2">
        @foreach ($righe as $i => $riga)
            @include($partial, ['i' => $i, 'riga' => $riga] + $dati)
        @endforeach
    </div>
    <template data-modello>
        @include($partial, ['i' => '__I__', 'riga' => null] + $dati)
    </template>
    <button type="button" data-aggiungi class="text-sm text-primary hover:underline cursor-pointer">+ {{ $etichetta }}</button>
</div>
