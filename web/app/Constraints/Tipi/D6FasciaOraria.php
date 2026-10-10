<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Constraints\DisciplineVincolo;

class D6FasciaOraria implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Fascia oraria vietata/preferita (D6)';
    }

    public function ambitiConsentiti(): array
    {
        return ['classe', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'disciplina_ids' => ['required', 'array', 'min:1'],
            'disciplina_ids.*' => ['integer', 'exists:discipline,id'],
            'tipo' => ['required', 'in:vietata,preferita'],
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer', 'exists:slot,id'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = DisciplineVincolo::nomi($parametri, '?');
        $n = count($parametri['slot_ids']);

        return $parametri['tipo'] === 'vietata'
            ? "{$disciplina}: vietata in {$n} slot selezionati."
            : "{$disciplina}: solo nei {$n} slot selezionati.";
    }
}
