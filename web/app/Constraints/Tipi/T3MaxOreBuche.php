<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class T3MaxOreBuche implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Max ore buche (T3)';
    }

    public function ambitiConsentiti(): array
    {
        return ['docente', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'max_per_giorno' => ['required_without:max_per_settimana', 'nullable', 'integer', 'min:0', 'max:6'],
            'max_per_settimana' => ['required_without:max_per_giorno', 'nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $parti = [];
        if (isset($parametri['max_per_giorno'])) {
            $parti[] = "max {$parametri['max_per_giorno']}/giorno";
        }
        if (isset($parametri['max_per_settimana'])) {
            $parti[] = "max {$parametri['max_per_settimana']}/settimana";
        }

        return 'Ore buche: '.implode(', ', $parti ?: ['nessun limite']).'.';
    }
}
