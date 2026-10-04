<?php

namespace App\Http\Controllers;

use App\Services\ControlloAggiornamenti;
use Illuminate\Http\JsonResponse;

class AggiornamentiController extends Controller
{
    /** Chiamato dalla pagina dopo il caricamento (resources/js/aggiornamenti.js): non rallenta mai la visualizzazione. */
    public function __invoke(ControlloAggiornamenti $controllo): JsonResponse
    {
        return response()->json(['disponibile' => $controllo->disponibile()]);
    }
}
