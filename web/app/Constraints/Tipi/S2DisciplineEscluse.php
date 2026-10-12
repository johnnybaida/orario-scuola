<?php

namespace App\Constraints\Tipi;

use App\Constraints\DisciplineVincolo;

class S2DisciplineEscluse extends S1DisciplinePreferite
{
    public function etichetta(): string
    {
        return 'Discipline escluse per il sostegno (S2)';
    }

    public function descrizione(array $parametri): string
    {
        return 'Il sostegno non sta nelle lezioni di: '.DisciplineVincolo::nomi($parametri).'.';
    }
}
