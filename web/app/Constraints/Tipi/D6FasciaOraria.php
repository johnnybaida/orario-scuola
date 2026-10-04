<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Models\Disciplina;

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
            'disciplina_id' => ['required', 'exists:discipline,id'],
            'tipo' => ['required', 'in:vietata,preferita'],
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer', 'exists:slot,id'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = Disciplina::query()->find($parametri['disciplina_id'])?->nome ?? '?';
        $n = count($parametri['slot_ids']);

        return $parametri['tipo'] === 'vietata'
            ? "{$disciplina}: vietata in {$n} slot selezionati."
            : "{$disciplina}: solo nei {$n} slot selezionati.";
    }
}
