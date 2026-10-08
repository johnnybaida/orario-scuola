<?php

namespace App\Http\Controllers;

use App\Models\Sospensione;
use App\Services\Substitution\SostituzioneCattedre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Passa le cattedre del docente sospeso ai supplenti indicati sulla sospensione e le riporta al rientro. */
class SostituzioneController extends Controller
{
    public function form(Sospensione $sospensione): View
    {
        $sospensione->load('docente', 'supplenti');

        return view('sostituzioni.form', [
            'sospensione' => $sospensione,
            'daAssegnare' => $sospensione->docente->cattedre()->with('classe', 'disciplina')->get()->sortBy(fn ($c) => $c->classe->nomeCompleto()),
            'assegnate' => $sospensione->cattedreSostituite()->with('classe', 'disciplina', 'docente')->get()->sortBy(fn ($c) => $c->classe->nomeCompleto()),
        ]);
    }

    public function assegna(Request $request, Sospensione $sospensione, SostituzioneCattedre $servizio): RedirectResponse
    {
        $n = $servizio->assegna($sospensione, $request->input('assegnazioni', []));

        return redirect()->route('sostituzioni.form', $sospensione)->with('successo', "Cattedre passate ai supplenti: {$n}.");
    }

    public function ripristina(Sospensione $sospensione, SostituzioneCattedre $servizio): RedirectResponse
    {
        $esito = $servizio->ripristina($sospensione);
        $messaggio = "Cattedre riportate al titolare: {$esito['ripristinate']}.";
        if ($esito['conflitti']) {
            $messaggio .= ' Restano al supplente perché il titolare le ha già: '.implode(', ', $esito['conflitti']).'.';
        }

        return redirect()->route('sostituzioni.form', $sospensione)->with('successo', $messaggio);
    }
}
