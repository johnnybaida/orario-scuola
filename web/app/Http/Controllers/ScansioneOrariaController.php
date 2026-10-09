<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScansioneOrariaRequest;
use App\Services\ScansioneOraria;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScansioneOrariaController extends Controller
{
    public function index(ScansioneOraria $scansione): View
    {
        return view('scansione.index', [
            'ore' => $scansione->ore(),
            'auleInPausa' => \App\Models\Aula::query()->where('tipo', \App\Enums\TipoAula::Pausa->value)->orderBy('nome')->get(),
        ]);
    }

    /** Per una sede senza scansione (appena creata): ore standard da cui partire. */
    public function standard(ScansioneOraria $scansione): RedirectResponse
    {
        abort_if($scansione->ore()->isNotEmpty(), 422, 'La scansione oraria esiste già.');
        $scansione->creaStandard();

        return redirect()->route('scansione.index')->with('successo', 'Scansione oraria standard creata: ritocca gli orari e salva.');
    }

    public function update(ScansioneOrariaRequest $request, ScansioneOraria $scansione): RedirectResponse
    {
        $scansione->applica($request->validated('ore'), $request->validated('pausa_prima') ?? []);

        return redirect()->route('scansione.index')->with('successo', 'Scansione oraria aggiornata.');
    }
}
