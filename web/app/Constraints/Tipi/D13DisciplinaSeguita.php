<?php

namespace App\Constraints\Tipi;

use App\Constraints\DisciplineVincolo;
use App\Constraints\VincoloTipoInterface;
use App\Models\Disciplina;

class D13DisciplinaSeguita implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Disciplina seguita da un\'altra (D13)';
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
            'seguite_ids' => ['required', 'array', 'min:1'],
            'seguite_ids.*' => ['integer', 'exists:discipline,id'],
            'modo' => ['required', 'in:segue,non_segue'],
            'min_coppie' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $prima = DisciplineVincolo::nomi($parametri, '?');
        $dopo = Disciplina::query()->whereIn('id', $parametri['seguite_ids'] ?? [])->orderBy('nome')->pluck('nome')->implode(', ') ?: '?';
        if (($parametri['modo'] ?? 'segue') === 'non_segue') {
            return "{$prima}: mai seguita subito dopo da {$dopo}.";
        }
        $min = $parametri['min_coppie'] ?? null;

        return $min ? "{$prima} seguita subito dopo da {$dopo}: almeno {$min} volte nella settimana, per classe." : "{$prima}: ogni lezione è seguita subito dopo da {$dopo}.";
    }
}
