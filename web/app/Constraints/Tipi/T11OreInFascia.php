<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Models\Slot;

class T11OreInFascia implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Ore minime in una fascia (T11)';
    }

    public function ambitiConsentiti(): array
    {
        return ['docente'];
    }

    public function regoleParametri(): array
    {
        return [
            'min_ore' => ['required', 'integer', 'min:1', 'max:30'],
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer', 'exists:slot,id'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $n = count($parametri['slot_ids'] ?? []);
        $min = (int) ($parametri['min_ore'] ?? 1);

        return "Almeno {$min} ".($min === 1 ? 'ora' : 'ore')." tra gli slot selezionati ({$n}).";
    }
}
