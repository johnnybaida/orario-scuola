<?php

namespace App\Constraints\Tipi;

use App\Constraints\DisciplineVincolo;
use App\Constraints\VincoloTipoInterface;

/** Il docente di sostegno sta (S1) solo nelle lezioni delle discipline scelte; S2 estende questa classe per il caso opposto. */
class S1DisciplinePreferite implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Discipline preferite per il sostegno (S1)';
    }

    public function ambitiConsentiti(): array
    {
        return ['globale', 'classe', 'docente'];
    }

    public function regoleParametri(): array
    {
        return [
            'disciplina_ids' => ['required', 'array', 'min:1'],
            'disciplina_ids.*' => ['integer', 'exists:discipline,id'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        return 'Il sostegno sta solo nelle lezioni di: '.DisciplineVincolo::nomi($parametri).'.';
    }
}
