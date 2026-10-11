<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class T12OreAttornoMensa implements VincoloTipoInterface
{
    public const MODI = [
        'prima_o_dopo' => 'prima o dopo la mensa (o entrambe)',
        'prima' => 'prima della mensa',
        'dopo' => 'dopo la mensa',
        'a_cavallo' => 'sia prima sia dopo la mensa',
    ];

    public function etichetta(): string
    {
        return 'Ore di chi sorveglia la mensa (T12)';
    }

    public function ambitiConsentiti(): array
    {
        return ['globale', 'docente'];
    }

    public function regoleParametri(): array
    {
        return [
            'modo' => ['required', 'in:'.implode(',', array_keys(self::MODI))],
            'min_ore' => ['required', 'integer', 'min:1', 'max:9'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $min = (int) ($parametri['min_ore'] ?? 1);
        $modo = self::MODI[$parametri['modo'] ?? 'prima_o_dopo'] ?? self::MODI['prima_o_dopo'];

        return "Nei giorni di sorveglianza della mensa il docente ha almeno {$min} ".($min === 1 ? 'ora' : 'ore').' di lezione o sostegno '.$modo.'.';
    }
}
