<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Avvia e ferma il worker di coda dall'interfaccia.
 * ponytail: un solo worker, tracciato da un PID file; non riparte dopo il riavvio della macchina
 * (per quello serve supervisor/systemd).
 */
class QueueWorker
{
    public function pidFile(): string
    {
        return storage_path('app/queue-worker.pid');
    }

    public function attivo(): bool
    {
        $pid = is_file($this->pidFile()) ? (int) trim(file_get_contents($this->pidFile())) : 0;

        // kill -0 non invia segnali: controlla solo che il processo esista.
        return $pid > 0 && (new Process(['kill', '-0', (string) $pid]))->run() === 0;
    }

    public function avvia(): bool
    {
        if ($this->attivo()) {
            return false;
        }

        // Il worker confronta il timestamp di restart all'avvio: un eventuale "ferma" precedente non lo uccide.
        $comando = sprintf(
            'nohup %s %s queue:work --tries=1 >> %s 2>&1 & echo $!',
            escapeshellarg((new PhpExecutableFinder)->find() ?: 'php'),
            escapeshellarg(base_path('artisan')),
            escapeshellarg(storage_path('logs/queue-worker.log')),
        );
        $processo = Process::fromShellCommandline($comando, base_path());
        $processo->run();
        file_put_contents($this->pidFile(), trim($processo->getOutput()));

        return true;
    }

    /** Arresto graceful: il worker finisce il job in corso (es. una generazione) e poi esce. */
    public function ferma(): void
    {
        Artisan::call('queue:restart');
    }
}
