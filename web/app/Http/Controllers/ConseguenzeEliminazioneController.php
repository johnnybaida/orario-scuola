<?php

namespace App\Http\Controllers;

use App\Services\ConseguenzeEliminazione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Anteprima di ciò che un'eliminazione porta via a cascata (usata da selezione-multipla.js prima di chiedere conferma). */
class ConseguenzeEliminazioneController extends Controller
{
    public function __invoke(Request $request, ConseguenzeEliminazione $servizio): JsonResponse
    {
        abort_unless(Gate::any(['gestisci-anagrafica', 'gestisci-docenti-classi', 'gestisci-utenze']), 403);
        $dati = $request->validate([
            'tabella' => ['required', 'in:'.implode(',', ConseguenzeEliminazione::TABELLE)],
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        return response()->json($servizio->per($dati['tabella'], array_map('intval', $dati['ids'])));
    }
}
