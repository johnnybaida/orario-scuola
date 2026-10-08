<?php

namespace App\Http\Controllers;

use App\Models\Impostazioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Impostazioni della sede in cui si lavora. */
class ImpostazioniController extends Controller
{
    public function index(): View
    {
        return view('impostazioni.index', ['impostazioni' => Impostazioni::correnti()->load('sede')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dati = $request->validate(['conteggio_sostegno' => ['required', 'in:'.implode(',', array_keys(Impostazioni::CONTEGGI))]]);
        Impostazioni::correnti()->update($dati);

        return redirect()->route('impostazioni.index')->with('successo', 'Impostazioni salvate.');
    }
}
