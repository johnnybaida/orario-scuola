<?php

namespace App\Http\Controllers;

use App\Http\Requests\CattedraRequest;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Services\StatoCattedre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CattedraController extends Controller
{
    public function index(Request $request, StatoCattedre $stato): View
    {
        $cattedre = Cattedra::query()
            ->with('docente', 'classe', 'disciplina')
            ->when($request->integer('classe_id'), fn ($q, $v) => $q->where('classe_id', $v))
            ->when($request->integer('docente_id'), fn ($q, $v) => $q->where('docente_id', $v))
            ->orderBy('classe_id')
            ->paginate(50)
            ->withQueryString();

        return view('cattedre.index', [
            'cattedre' => $cattedre,
            'stati' => $stato->per($cattedre->getCollection()),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'docenti' => Docente::query()->orderBy('cognome')->get(),
            'filtroClasse' => $request->integer('classe_id'),
            'filtroDocente' => $request->integer('docente_id'),
        ]);
    }

    public function create(): View
    {
        return view('cattedre.create', $this->opzioniForm());
    }

    public function store(CattedraRequest $request): RedirectResponse
    {
        Cattedra::query()->create($request->validated());

        return redirect()->route('cattedre.index')->with('successo', 'Cattedra creata.');
    }

    public function edit(Cattedra $cattedra): View
    {
        return view('cattedre.edit', array_merge($this->opzioniForm(), ['cattedra' => $cattedra]));
    }

    public function update(CattedraRequest $request, Cattedra $cattedra): RedirectResponse
    {
        $cattedra->update($request->validated());

        return redirect()->route('cattedre.index')->with('successo', 'Cattedra aggiornata.');
    }

    public function destroy(Cattedra $cattedra): RedirectResponse
    {
        $cattedra->delete();

        return redirect()->route('cattedre.index')->with('successo', 'Cattedra eliminata.');
    }

    private function opzioniForm(): array
    {
        return [
            'docenti' => Docente::query()->orderBy('cognome')->get(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ];
    }
}
