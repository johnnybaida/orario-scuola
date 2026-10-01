<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssegnazioneSostegnoRequest;
use App\Http\Requests\FabbisognoSostegnoRequest;
use App\Models\AssegnazioneSostegno;
use App\Models\Classe;
use App\Models\FabbisognoSostegno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SostegnoController extends Controller
{
    public function storeFabbisogno(FabbisognoSostegnoRequest $request, Classe $classe): RedirectResponse
    {
        $classe->fabbisogniSostegno()->create($request->validated());

        return back()->with('successo', 'Fabbisogno aggiunto.');
    }

    public function destroyFabbisogno(Classe $classe, FabbisognoSostegno $fabbisogno): RedirectResponse
    {
        abort_if($fabbisogno->classe_id !== $classe->id, 404);
        $fabbisogno->delete();

        return back()->with('successo', 'Fabbisogno rimosso.');
    }

    public function storeAssegnazione(AssegnazioneSostegnoRequest $request, Classe $classe): RedirectResponse
    {
        $classe->assegnazioniSostegno()->create($request->validated());

        return back()->with('successo', 'Assegnazione aggiunta.');
    }

    public function destroyAssegnazione(Classe $classe, AssegnazioneSostegno $assegnazione): RedirectResponse
    {
        abort_if($assegnazione->classe_id !== $classe->id, 404);
        $assegnazione->delete();

        return back()->with('successo', 'Assegnazione rimossa.');
    }

    public function updateConteggio(Request $request, Classe $classe): RedirectResponse
    {
        $dati = $request->validate(['conteggio_sostegno' => ['nullable', 'in:per_alunno,per_classe']]);
        $classe->update(['conteggio_sostegno' => $dati['conteggio_sostegno'] ?? null]);

        return back()->with('successo', 'Conteggio sostegno aggiornato.');
    }
}
