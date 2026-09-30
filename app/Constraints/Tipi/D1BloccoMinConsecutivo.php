<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Models\Disciplina;

class D1BloccoMinConsecutivo implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Blocco consecutivo minimo (D1)';
    }

    public function ambitiConsentiti(): array
    {
        return ['classe', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'disciplina_id' => ['required', 'exists:discipline,id'],
            'min_consecutive' => ['required', 'integer', 'min:2', 'max:6'],
            'n_blocchi_min' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = Disciplina::query()->find($parametri['disciplina_id'])?->nome ?? '?';
        $nBlocchi = $parametri['n_blocchi_min'] ?? 1;

        return "{$disciplina}: almeno {$nBlocchi} blocco/i da {$parametri['min_consecutive']} ore consecutive a settimana.";
    }
}
