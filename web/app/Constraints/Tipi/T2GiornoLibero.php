<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class T2GiornoLibero implements VincoloTipoInterface
{
    private const GIORNI = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Gio', 5 => 'Ven', 6 => 'Sab'];

    public function etichetta(): string
    {
        return 'Giorno libero (T2)';
    }

    public function ambitiConsentiti(): array
    {
        return ['docente', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'n_giorni' => ['required', 'integer', 'min:1', 'max:3'],
            'preferenze' => ['nullable', 'array'],
            'preferenze.*' => ['integer', 'min:1', 'max:6'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $preferenze = collect($parametri['preferenze'] ?? [])->map(fn ($g) => self::GIORNI[$g] ?? $g)->implode(', ');
        $suffisso = $preferenze ? " (preferenza: {$preferenze})" : '';

        return "Almeno {$parametri['n_giorni']} giorno/i libero/i a settimana{$suffisso}.";
    }
}
