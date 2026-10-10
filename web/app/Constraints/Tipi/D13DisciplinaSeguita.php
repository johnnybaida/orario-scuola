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
            'clil_prima' => ['nullable', 'in:tutte,con,senza'],
            'clil_dopo' => ['nullable', 'in:tutte,con,senza'],
            'inverso' => ['nullable', 'boolean'],
        ];
    }

    /** «Geografia con CLIL» / «Geografia senza CLIL» / «Geografia». */
    private function conClil(string $nomi, ?string $clil): string
    {
        return match ($clil) {
            'con' => "{$nomi} con CLIL",
            'senza' => "{$nomi} senza CLIL",
            default => $nomi,
        };
    }

    public function descrizione(array $parametri): string
    {
        $prima = $this->conClil(DisciplineVincolo::nomi($parametri, '?'), $parametri['clil_prima'] ?? null);
        $dopo = $this->conClil(Disciplina::query()->whereIn('id', $parametri['seguite_ids'] ?? [])->orderBy('nome')->pluck('nome')->implode(', ') ?: '?', $parametri['clil_dopo'] ?? null);
        if (($parametri['modo'] ?? 'segue') === 'non_segue') {
            return "{$prima}: mai seguita subito dopo da {$dopo}".(! empty($parametri['inverso']) ? ' (né nell\'ordine inverso)' : '').'.';
        }
        $min = $parametri['min_coppie'] ?? null;

        return $min ? "{$prima} seguita subito dopo da {$dopo}: almeno {$min} volte nella settimana, per classe." : "{$prima}: ogni lezione è seguita subito dopo da {$dopo}.";
    }
}
