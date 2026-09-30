<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuadroOrarioRequest;
use App\Models\Disciplina;
use App\Models\QuadroOrario;
use App\Models\QuadroOrarioRiga;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuadroOrarioController extends Controller
{
    public function index(): View
    {
        return view('quadri-orari.index', [
            'quadri' => QuadroOrario::query()->withCount('classi')->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        return view('quadri-orari.create');
    }

    public function store(QuadroOrarioRequest $request): RedirectResponse
    {
        $quadro = QuadroOrario::query()->create($request->validated());

        return redirect()->route('quadri-orari.edit', $quadro)->with('successo', 'Quadro orario creato. Aggiungi ora le discipline.');
    }

    public function edit(QuadroOrario $quadroOrario): View
    {
        return view('quadri-orari.edit', [
            'quadro' => $quadroOrario->load('righe.disciplina'),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function update(QuadroOrarioRequest $request, QuadroOrario $quadroOrario): RedirectResponse
    {
        $quadroOrario->update($request->validated());

        return redirect()->route('quadri-orari.edit', $quadroOrario)->with('successo', 'Quadro orario aggiornato.');
    }

    public function destroy(QuadroOrario $quadroOrario): RedirectResponse
    {
        $quadroOrario->delete();

        return redirect()->route('quadri-orari.index')->with('successo', 'Quadro orario eliminato.');
    }

    public function storeRiga(Request $request, QuadroOrario $quadroOrario): RedirectResponse
    {
        $dati = $request->validate([
            'disciplina_id' => ['required', 'exists:discipline,id'],
            'ore_settimanali' => ['required', 'integer', 'min:1', 'max:40'],
        ]);

        $quadroOrario->righe()->updateOrCreate(
            ['disciplina_id' => $dati['disciplina_id']],
            ['ore_settimanali' => $dati['ore_settimanali']],
        );

        $quadroOrario->update(['ore_totali' => $quadroOrario->righe()->sum('ore_settimanali')]);

        return back()->with('successo', 'Riga aggiunta al quadro orario.');
    }

    public function destroyRiga(QuadroOrarioRiga $riga): RedirectResponse
    {
        $quadro = $riga->quadroOrario;
        $riga->delete();
        $quadro->update(['ore_totali' => $quadro->righe()->sum('ore_settimanali')]);

        return back()->with('successo', 'Riga rimossa dal quadro orario.');
    }
}
