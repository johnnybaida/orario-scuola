<?php

namespace App\Jobs;

use App\Models\Generazione;
use App\Services\Solver\ProblemBuilder;
use App\Services\Solver\ResultImporter;
use App\Services\Solver\SolverRunner;
use App\Services\Validation\PreValidator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateTimetable implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout;

    public function __construct(private readonly int $generazioneId)
    {
        // margine oltre al time limit del solver, per non essere uccisi dal worker prima che risponda.
        $this->timeout = 600;
    }

    public function handle(
        PreValidator $preValidator,
        ProblemBuilder $problemBuilder,
        SolverRunner $solverRunner,
        ResultImporter $resultImporter,
    ): void {
        $generazione = Generazione::query()->findOrFail($this->generazioneId);
        // Il worker non ha una sessione: si lavora nella sede della generazione (con la coda «sync» è già quella in uso).
        app(\App\Services\SedeCorrente::class)->imposta($generazione->sede_id);
        $generazione->update(['stato' => 'in_corso', 'progresso' => 10]);

        $problemi = $preValidator->esegui();
        if ($problemi) {
            $generazione->update(['stato' => 'infattibile', 'progresso' => 100, 'diagnostica' => $problemi]);

            return;
        }

        $generazione->update(['progresso' => 30]);
        $problema = $problemBuilder->costruisci($generazione->seed, $generazione->time_limit_s);

        $generazione->update(['progresso' => 50]);
        $risultato = $solverRunner->esegui($problema);

        if (in_array($risultato['stato'], ['infattibile', 'timeout'], true)) {
            $generazione->update([
                'stato' => 'infattibile',
                'progresso' => 100,
                'diagnostica' => $risultato['diagnostica'],
            ]);

            return;
        }

        $orario = $resultImporter->importa(
            $generazione->periodo,
            $generazione->seed,
            $risultato,
            $problemBuilder->mappaLezioni(),
            $generazione->creato_da,
            $generazione->nome,
        );

        $generazione->update([
            'stato' => 'completata',
            'progresso' => 100,
            'orario_id' => $orario->id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Generazione::query()->find($this->generazioneId)?->update([
            'stato' => 'fallita',
            'progresso' => 100,
            'diagnostica' => [get_class($e).' in '.basename($e->getFile()).':'.$e->getLine().' - '.$e->getMessage()],
        ]);
    }
}
