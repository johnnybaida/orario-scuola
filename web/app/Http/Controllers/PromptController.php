<?php

namespace App\Http\Controllers;

use App\Services\PromptOrario;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PromptController extends Controller
{
    /** Pagina con il testo da copiare; `?scarica=1` lo scarica come file. */
    public function index(Request $request, PromptOrario $prompt): View|Response
    {
        $testo = $prompt->testo();

        if ($request->boolean('scarica')) {
            return response($testo, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="prompt-orario.txt"']);
        }

        return view('prompt.index', ['testo' => $testo]);
    }
}
