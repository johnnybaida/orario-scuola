<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Models\Disciplina;

class D12BloccoMaxConsecutivo implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Blocco consecutivo massimo (D12)';
    }

    public function ambitiConsentiti(): array
    {
        return ['classe', 'globale', 'docente'];
    }

    public function regoleParametri(): array
    {
        return [
            // Facoltativa solo con ambito Docente (vedi erroriAmbito): senza disciplina contano tutte le lezioni del docente.
            'disciplina_id' => ['nullable', 'exists:discipline,id'],
            'max_consecutive' => ['required', 'integer', 'min:1', 'max:8'],
        ];
    }

    /** @return array<string, string> campo => messaggio */
    public function erroriAmbito(string $ambito, array $parametri): array
    {
        return $ambito !== 'docente' && empty($parametri['disciplina_id'])
            ? ['parametri.disciplina_id' => 'La disciplina è obbligatoria, tranne con Ambito = Docente.'] : [];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = empty($parametri['disciplina_id']) ? 'Tutte le lezioni' : (Disciplina::query()->find($parametri['disciplina_id'])?->nome ?? '?');
        $max = (int) $parametri['max_consecutive'];

        return $max === 1 ? "{$disciplina}: mai due ore consecutive nello stesso giorno." : "{$disciplina}: al massimo {$max} ore consecutive nello stesso giorno.";
    }
}
