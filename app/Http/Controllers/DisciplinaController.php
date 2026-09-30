<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisciplinaRequest;
use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DisciplinaController extends Controller
{
    public function index(): View
    {
        return view('discipline.index', [
            'discipline' => Disciplina::query()->with('padre')->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        return view('discipline.create', [
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
            'tipiAula' => $this->tipiAula(),
        ]);
    }

    public function store(DisciplinaRequest $request): RedirectResponse
    {
        Disciplina::query()->create($request->validated());

        return redirect()->route('discipline.index')->with('successo', 'Disciplina creata.');
    }

    public function edit(Disciplina $disciplina): View
    {
        return view('discipline.edit', [
            'disciplina' => $disciplina,
            'discipline' => Disciplina::query()->where('id', '!=', $disciplina->id)->orderBy('nome')->get(),
            'tipiAula' => $this->tipiAula(),
        ]);
    }

    public function update(DisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $disciplina->update($request->validated());

        return redirect()->route('discipline.index')->with('successo', 'Disciplina aggiornata.');
    }

    public function destroy(Disciplina $disciplina): RedirectResponse
    {
        $disciplina->delete();

        return redirect()->route('discipline.index')->with('successo', 'Disciplina eliminata.');
    }

    private function tipiAula(): array
    {
        return Aula::query()->distinct()->orderBy('tipo')->pluck('tipo')->all();
    }
}
