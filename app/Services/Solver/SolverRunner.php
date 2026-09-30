<?php

namespace App\Services\Solver;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Lancia solver/solver.py (OR-Tools CP-SAT) passandogli il problema JSON su
 * stdin e leggendo la soluzione JSON da stdout. Uscita != 0 => errore tecnico
 * (l'infattibilità è un JSON valido con stato "infattibile", non un errore).
 */
class SolverRunner
{
    public function esegui(array $problema): array
    {
        $python = base_path('solver/.venv/bin/python');
        $timeout = ($problema['time_limit_s'] ?? 120) + 30;

        $risultato = Process::path(base_path('solver'))
            ->timeout($timeout)
            ->input(json_encode($problema))
            ->run("{$python} solver.py");

        if ($risultato->failed()) {
            throw new RuntimeException("Errore tecnico del solver: {$risultato->errorOutput()}");
        }

        return json_decode($risultato->output(), associative: true);
    }
}
