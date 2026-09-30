<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Models\Disciplina;

class D3MaxOreGiorno implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Max ore/giorno per disciplina (D3)';
    }

    public function ambitiConsentiti(): array
    {
        return ['classe', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'disciplina_id' => ['required', 'exists:discipline,id'],
            'max' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = Disciplina::query()->find($parametri['disciplina_id'])?->nome ?? '?';

        return "{$disciplina}: massimo {$parametri['max']} ora/e al giorno.";
    }
}
