<?php

namespace App\Http\Controllers;

use App\Services\QueueWorker;
use Illuminate\Http\RedirectResponse;

class WorkerController extends Controller
{
    public function avvia(QueueWorker $worker): RedirectResponse
    {
        $messaggio = $worker->avvia() ? 'Worker di coda avviato.' : 'Il worker di coda è già attivo.';

        return back()->with('successo', $messaggio);
    }

    public function ferma(QueueWorker $worker): RedirectResponse
    {
        $worker->ferma();

        return back()->with('successo', 'Arresto richiesto: il worker termina il job in corso e poi si ferma.');
    }
}
