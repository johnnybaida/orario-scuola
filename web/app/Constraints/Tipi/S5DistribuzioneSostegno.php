<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class S5DistribuzioneSostegno implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Distribuzione del sostegno (S5)';
    }

    public function ambitiConsentiti(): array
    {
        return ['globale', 'classe'];
    }

    public function regoleParametri(): array
    {
        return [
            'max_insieme' => ['nullable', 'integer', 'min:1', 'max:6'],
            'tolleranza_giorno' => ['nullable', 'integer', 'min:0', 'max:6'],
        ];
    }

    /** @return array<string, string> campo => messaggio */
    public function erroriAmbito(string $ambito, array $parametri): array
    {
        return ($parametri['max_insieme'] ?? '') === '' && ($parametri['tolleranza_giorno'] ?? '') === ''
            ? ['parametri.max_insieme' => 'Compila almeno uno dei due limiti: docenti insieme o tolleranza giornaliera.'] : [];
    }

    public function descrizione(array $parametri): string
    {
        $parti = [];
        if (($parametri['max_insieme'] ?? '') !== '') {
            $parti[] = "al massimo {$parametri['max_insieme']} docente/i di sostegno insieme nella stessa classe e ora";
        }
        if (($parametri['tolleranza_giorno'] ?? '') !== '') {
            $parti[] = "ore di sostegno distribuite nella settimana (al massimo la media giornaliera + {$parametri['tolleranza_giorno']} ora/e per giorno)";
        }

        return ucfirst(implode('; ', $parti)).'.';
    }
}
