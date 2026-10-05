<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerazioneRequest;
use App\Jobs\GenerateTimetable;
use App\Models\Generazione;
use App\Models\Periodo;
use App\Services\Diagnostica;
use App\Services\QueueWorker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenerazioneController extends Controller
{
    public function index(): View
    {
        return view('generazioni.index', [
            'generazioni' => Generazione::query()->with('orario')->orderByDesc('id')->get(),
            'workerAttivo' => app(\App\Services\QueueWorker::class)->attivo(),
            'workerInArresto' => app(\App\Services\QueueWorker::class)->inArresto(),
        ]);
    }

    public function create(): View
    {
        // Seed già usati, con l'orario che hanno prodotto: riusarne uno (a dati invariati) riproduce lo stesso orario.
        $seedUsati = Generazione::query()->with('orario')->whereNotNull('orario_id')->orderByDesc('id')->get()
            ->unique('seed')->map(fn (Generazione $g) => ['seed' => $g->seed, 'etichetta' => ($g->orario?->etichetta() ?? 'Generazione n. '.$g->id).' (seed '.$g->seed.')']);

        return view('generazioni.create', ['seedUsati' => $seedUsati]);
    }

    public function store(GenerazioneRequest $request, QueueWorker $worker): RedirectResponse
    {
        $generazione = Generazione::query()->create([
            'periodo_id' => Periodo::corrente()->id,
            // random_seed di OR-Tools è un int32: il seed deve starci dentro.
            'nome' => $request->input('nome') ?: null,
            'seed' => $request->input('seed') ?: random_int(1, 2147483647),
            'time_limit_s' => $request->input('time_limit_s'),
            'stato' => 'in_coda',
            'creato_da' => $request->user()->id,
        ]);

        GenerateTimetable::dispatch($generazione->id);

        $redirect = redirect()->route('generazioni.show', $generazione);

        // Con la coda "sync" il job è già stato eseguito; altrimenti serve un worker, e se è fermo lo avviamo noi.
        if (config('queue.default') !== 'sync') {
            try {
                if ($worker->avvia()) {
                    $redirect->with('successo', 'Il worker di coda era fermo: l\'ho avviato.');
                }
            } catch (\RuntimeException $e) {
                $redirect->withErrors(['worker' => "Generazione in coda, ma il worker non è partito: {$e->getMessage()}"]);
            }
        }

        return $redirect;
    }

    public function show(Generazione $generazione): View
    {
        return view('generazioni.show', ['generazione' => $generazione->load('orario')]);
    }

    public function stato(Generazione $generazione): JsonResponse
    {
        return response()->json([
            'stato' => $generazione->stato,
            'progresso' => $generazione->progresso,
            'diagnostica' => $generazione->diagnostica,
            'orario_id' => $generazione->orario_id,
        ]);
    }

    public function diagnostica(Generazione $generazione, Diagnostica $diagnostica)
    {
        return response($diagnostica->rapporto($generazione), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="diagnostica-generazione-'.$generazione->id.'.txt"',
        ]);
    }
}
