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

    private function pid(): int
    {
        return is_file($this->pidFile()) ? (int) trim(file_get_contents($this->pidFile())) : 0;
    }

    public function attivo(): bool
    {
        $pid = $this->pid();

        return $pid > 0 && $this->processoVivo($pid);
    }

    /** Segnale 0: non invia nulla, controlla solo che il processo esista (senza dipendere dal PATH del web server). */
    private function processoVivo(int $pid): bool
    {
        return function_exists('posix_kill')
            ? posix_kill($pid, 0)
            : (new Process(['ps', '-p', (string) $pid]))->run() === 0;
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
        // trap '' HUP + exec = come nohup (SIGHUP ignorato, stesso PID) senza dipendere dal comando nohup.
        $log = storage_path('logs/queue-worker.log');
        $offsetLog = is_file($log) ? filesize($log) : 0;
        $comando = sprintf(
            "(trap '' HUP; exec %s %s queue:work --tries=1) < /dev/null >> %s 2>&1 & echo $!",
            escapeshellarg((new PhpExecutableFinder)->find() ?: 'php'),
            escapeshellarg(base_path('artisan')),
            escapeshellarg($log),
        );
        $processo = Process::fromShellCommandline($comando, base_path());
        $processo->run();
        file_put_contents($this->pidFile(), trim($processo->getOutput()));

        usleep(1_500_000);
        if (! $this->attivo()) {
            // Solo l'output di questo avvio, non quello degli avvii precedenti.
            $nuovo = trim(substr((string) file_get_contents($log), $offsetLog));
            throw new \RuntimeException("Il worker si è fermato subito dopo l'avvio (PID {$this->pid()}). ".($nuovo ?: 'Nessun output nel log: controlla storage/logs/queue-worker.log.'));
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
