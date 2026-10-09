<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuadroOrarioRequest;
use App\Models\Disciplina;
use App\Models\QuadroOrario;
use App\Services\SincronizzaRighe;
use Illuminate\Http\RedirectResponse;
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
        return view('quadri-orari.create', ['righe' => [], 'discipline' => Disciplina::query()->orderBy('nome')->get()]);
    }

    public function store(QuadroOrarioRequest $request): RedirectResponse
    {
        $quadro = QuadroOrario::query()->create($request->safe()->only('nome'));
        $this->salvaRighe($request, $quadro);

        return redirect()->route('quadri-orari.index')->with('successo', 'Quadro orario creato.');
    }

    public function edit(QuadroOrario $quadroOrario): View
    {
        return view('quadri-orari.edit', [
            'quadro' => $quadroOrario,
            'righe' => $quadroOrario->righe()->with('disciplina')->get()->sortBy('disciplina.nome')
                ->map(fn ($r) => ['id' => $r->id, 'disciplina_id' => $r->disciplina_id, 'ore_settimanali' => $r->ore_settimanali])->all(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function update(QuadroOrarioRequest $request, QuadroOrario $quadroOrario): RedirectResponse
    {
        $quadroOrario->update($request->safe()->only('nome'));

        $this->salvaRighe($request, $quadroOrario);

        return redirect()->route('quadri-orari.index')->with('successo', 'Quadro orario aggiornato.');
    }

    public function destroy(QuadroOrario $quadroOrario): RedirectResponse
    {
        // Le classi cadono in cascata col quadro: non si elimina un quadro in uso.
        abort_if($quadroOrario->classi()->exists(), 422, 'Quadro orario usato da alcune classi.');

        $quadroOrario->delete();

        return redirect()->route('quadri-orari.index')->with('successo', 'Quadro orario eliminato.');
    }

    private function salvaRighe(QuadroOrarioRequest $request, QuadroOrario $quadro): void
    {
        if (! $request->boolean('sezioni_extra')) {
            return;
        }

        SincronizzaRighe::applica($quadro->righe(), $request->input('righe', []), ['disciplina_id', 'ore_settimanali']);
        // Le ore di mensa (pausa pranzo) stanno nel quadro ma non sono discipline: il totale le comprende, gli slot attivi no.
        $quadro->update(['ore_mensa' => (int) $request->input('ore_mensa', 0), 'ore_totali' => $quadro->righe()->sum('ore_settimanali') + (int) $request->input('ore_mensa', 0)]);
    }
}
