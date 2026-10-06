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
        return view('scansione.index', ['ore' => $scansione->ore()]);
    }

    public function update(ScansioneOrariaRequest $request, ScansioneOraria $scansione): RedirectResponse
    {
        $scansione->applica($request->validated('ore'));

        return redirect()->route('scansione.index')->with('successo', 'Scansione oraria aggiornata.');
    }
}
