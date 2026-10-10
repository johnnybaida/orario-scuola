<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Constraints\DisciplineVincolo;

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
            'disciplina_ids' => ['required', 'array', 'min:1'],
            'disciplina_ids.*' => ['integer', 'exists:discipline,id'],
            'max' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = DisciplineVincolo::nomi($parametri, '?');

        return "{$disciplina}: massimo {$parametri['max']} ora/e al giorno.";
    }
}
