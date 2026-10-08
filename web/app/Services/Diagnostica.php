<?php

namespace App\Services;

use App\Models\Generazione;
use App\Models\Vincolo;
use App\Services\Solver\ProblemBuilder;
use App\Services\Validation\PreValidator;

/**
 * Rapporto di testo per l'assistenza: tutto ciò che serve a capire perché una generazione è andata male,
 * da scaricare dall'interfaccia e inviare a chi sviluppa. Solo lettura, nessuna chiamata esterna.
 */
class Diagnostica
{
    public function rapporto(Generazione $g): string
    {
        $sezioni = [
            'Orario Scuola - rapporto diagnostico',
            'Creato: '.now()->toDateTimeString().' | versione app: '.config('app.versione').' | PHP '.PHP_VERSION.' | coda: '.config('queue.default'),
            $this->sezione('GENERAZIONE', $this->generazione($g)),
            $this->sezione('ULTIME GENERAZIONI', $this->ultime()),
            $this->sezione('VINCOLI ATTIVI', $this->vincoli()),
            $this->sezione('PRE-VALIDAZIONE (problemi sui dati)', $this->prevalidazione()),
            $this->sezione('LOG DELL\'APPLICAZIONE (ultime righe)', $this->log()),
            $this->sezione('PROBLEMA INVIATO AL SOLVER (JSON ricostruito dai dati attuali; per riprodurre: solver/.venv/bin/python solver.py < problema.json)', $this->problema($g)),
        ];

        return implode("\n\n", $sezioni)."\n";
    }

    private function sezione(string $titolo, string $corpo): string
    {
        return "=== {$titolo} ===\n".($corpo !== '' ? $corpo : '(vuoto)');
    }

    private function generazione(Generazione $g): string
    {
        return "id: {$g->id}\nstato: {$g->stato}\nseed: {$g->seed}\ntempo limite: {$g->time_limit_s}s\ncreata: {$g->created_at}\n"
            ."diagnostica:\n".implode("\n---\n", $g->diagnostica ?? ['(nessuna)']);
    }

    private function ultime(): string
    {
        return Generazione::query()->orderByDesc('id')->limit(10)->get()
            ->map(fn (Generazione $x) => "#{$x->id} {$x->created_at} {$x->stato} seed {$x->seed}".($x->diagnostica ? ' - '.strtok($x->diagnostica[0], "\n") : ''))
            ->implode("\n");
    }

    private function vincoli(): string
    {
        return Vincolo::attivi()->get()
            ->map(fn (Vincolo $v) => "#{$v->id} {$v->tipo} | ambito {$v->ambito_livello} ".json_encode($v->ambito_ids)
                ." | {$v->severita}".($v->peso ? " peso {$v->peso}" : '').' | parametri '.json_encode($v->parametri, JSON_UNESCAPED_UNICODE))
            ->implode("\n");
    }

    private function prevalidazione(): string
    {
        return implode("\n", app(PreValidator::class)->esegui()) ?: 'nessun problema';
    }

    private function log(): string
    {
        $file = storage_path('logs/laravel.log');
        if (! is_readable($file)) {
            return 'laravel.log non disponibile (i log potrebbero andare su stderr: `docker compose logs app`)';
        }

        // Ultime ~200 righe, ciascuna accorciata: le stack trace sono lunghe e solo le prime righe servono.
        // Si legge solo la coda del file: il log può pesare decine di MB.
        $f = fopen($file, 'r');
        fseek($f, max(0, filesize($file) - 300_000));
        $righe = array_slice(explode("\n", rtrim((string) stream_get_contents($f))), -200);
        fclose($f);

        return implode("\n", array_map(fn ($r) => mb_substr($r, 0, 400), $righe));
    }

    private function problema(Generazione $g): string
    {
        try {
            return json_encode(app(ProblemBuilder::class)->costruisci($g->seed, $g->time_limit_s), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return 'Impossibile costruire il problema: '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';
        }
    }
}
