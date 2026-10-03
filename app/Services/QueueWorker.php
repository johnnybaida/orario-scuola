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

    private function fileArresto(): string
    {
        return storage_path('app/queue-worker.stop');
    }

    /** Arresto richiesto ma il worker sta ancora finendo il job in corso. */
    public function inArresto(): bool
    {
        return $this->attivo() && is_file($this->fileArresto());
    }

    public function attivo(): bool
    {
        $pid = is_file($this->pidFile()) ? (int) trim(file_get_contents($this->pidFile())) : 0;

        // kill -0 non invia segnali: controlla solo che il processo esista.
        return $pid > 0 && (new Process(['kill', '-0', (string) $pid]))->run() === 0;
    }

    /**
     * @return bool false se era già attivo (e non in arresto)
     *
     * @throws \RuntimeException se il processo muore subito (con le ultime righe del log)
     */
    public function avvia(): bool
    {
        if ($this->attivo() && ! $this->inArresto()) {
            return false;
        }
        @unlink($this->fileArresto());

        // Il worker confronta il timestamp di restart all'avvio: un eventuale "ferma" precedente non lo uccide.
        $comando = sprintf(
            'nohup %s %s queue:work --tries=1 < /dev/null >> %s 2>&1 & echo $!',
            escapeshellarg((new PhpExecutableFinder)->find() ?: 'php'),
            escapeshellarg(base_path('artisan')),
            escapeshellarg(storage_path('logs/queue-worker.log')),
        );
        $processo = Process::fromShellCommandline($comando, base_path());
        $processo->run();
        file_put_contents($this->pidFile(), trim($processo->getOutput()));

        usleep(1_500_000);
        if (! $this->attivo()) {
            $log = array_slice(file(storage_path('logs/queue-worker.log'), FILE_IGNORE_NEW_LINES) ?: [], -5);
            throw new \RuntimeException("Il worker si è fermato subito dopo l'avvio. ".implode(' | ', $log));
        }

        return true;
    }

    /** Arresto graceful: il worker finisce il job in corso (es. una generazione) e poi esce. */
    public function ferma(): void
    {
        Artisan::call('queue:restart');
        touch($this->fileArresto());
    }
}
