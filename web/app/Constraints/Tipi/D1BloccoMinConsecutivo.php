<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Constraints\DisciplineVincolo;

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
            'disciplina_ids' => ['nullable', 'array'],
            'disciplina_ids.*' => ['integer', 'exists:discipline,id'],
            'min_consecutive' => ['required', 'integer', 'min:2', 'max:6'],
            'n_blocchi_min' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }

    /** @return array<string, string> campo => messaggio */
    public function erroriAmbito(string $ambito, array $parametri): array
    {
        return $ambito !== 'docente' && ! DisciplineVincolo::ids($parametri)
            ? ['parametri.disciplina_ids' => 'Scegli almeno una disciplina (facoltativo solo con Ambito = Docente).'] : [];
    }

    public function descrizione(array $parametri): string
    {
        $disciplina = DisciplineVincolo::nomi($parametri);
        $nBlocchi = $parametri['n_blocchi_min'] ?? 1;

        return "{$disciplina}: almeno {$nBlocchi} blocco/i da {$parametri['min_consecutive']} ore consecutive a settimana.";
    }
}
