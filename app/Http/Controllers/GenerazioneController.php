<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerazioneRequest;
use App\Jobs\GenerateTimetable;
use App\Models\Generazione;
use App\Models\Periodo;
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
        return view('generazioni.create');
    }

    public function store(GenerazioneRequest $request): RedirectResponse
    {
        $generazione = Generazione::query()->create([
            'periodo_id' => Periodo::corrente()->id,
            // random_seed di OR-Tools è un int32: il seed deve starci dentro.
            'seed' => $request->input('seed') ?? random_int(1, 2147483647),
            'time_limit_s' => $request->input('time_limit_s'),
            'stato' => 'in_coda',
            'creato_da' => $request->user()->id,
        ]);

        GenerateTimetable::dispatch($generazione->id);

        return redirect()->route('generazioni.show', $generazione);
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
}
