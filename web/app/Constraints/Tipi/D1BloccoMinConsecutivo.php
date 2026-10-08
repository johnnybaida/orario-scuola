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
        return ['classe', 'globale', 'docente'];
    }

    public function regoleParametri(): array
    {
        return [
            // Facoltativa solo con ambito Docente (vedi erroriAmbito): senza disciplina contano tutte le lezioni del docente.
            'disciplina_id' => ['nullable', 'exists:discipline,id'],
            'min_consecutive' => ['required', 'integer', 'min:2', 'max:6'],
            'n_blocchi_min' => ['nullable', 'integer', 'min:1', 'max:5'],
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
        $nBlocchi = $parametri['n_blocchi_min'] ?? 1;

        return "{$disciplina}: almeno {$nBlocchi} blocco/i da {$parametri['min_consecutive']} ore consecutive a settimana.";
    }
}
