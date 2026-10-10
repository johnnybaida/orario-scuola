<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;
use App\Constraints\DisciplineVincolo;

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
            'disciplina_ids' => ['nullable', 'array'],
            'disciplina_ids.*' => ['integer', 'exists:discipline,id'],
            'max_consecutive' => ['required', 'integer', 'min:1', 'max:8'],
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
        $max = (int) $parametri['max_consecutive'];

        return $max === 1 ? "{$disciplina}: mai due ore consecutive nello stesso giorno." : "{$disciplina}: al massimo {$max} ore consecutive nello stesso giorno.";
    }
}
