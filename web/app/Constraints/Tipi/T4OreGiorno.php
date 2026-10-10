<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class T4OreGiorno implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Ore minime/massime al giorno (T4)';
    }

    public function ambitiConsentiti(): array
    {
        return ['docente', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'min_ore' => ['nullable', 'integer', 'min:1', 'max:9'],
            'max_ore' => ['nullable', 'integer', 'min:1', 'max:9'],
        ];
    }

    /** @return array<string, string> campo => messaggio */
    public function erroriAmbito(string $ambito, array $parametri): array
    {
        $min = ($parametri['min_ore'] ?? '') === '' ? null : (int) $parametri['min_ore'];
        $max = ($parametri['max_ore'] ?? '') === '' ? null : (int) $parametri['max_ore'];

        if ($min === null && $max === null) {
            return ['parametri.min_ore' => 'Compila almeno uno dei due limiti: ore minime o ore massime al giorno.'];
        }

        return $min !== null && $max !== null && $min > $max ? ['parametri.max_ore' => 'Le ore massime non possono essere meno delle minime.'] : [];
    }

    public function descrizione(array $parametri): string
    {
        $min = ($parametri['min_ore'] ?? '') === '' ? null : (int) $parametri['min_ore'];
        $max = ($parametri['max_ore'] ?? '') === '' ? null : (int) $parametri['max_ore'];
        $parti = array_filter([$min ? "almeno {$min} ora/e" : null, $max ? "al massimo {$max} ora/e" : null]);

        return 'Ogni giorno in cui il docente può esserci: '.implode(' e ', $parti).' al giorno.';
    }
}
