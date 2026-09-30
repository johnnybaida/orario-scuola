<?php

namespace App\Http\Controllers;

use App\Http\Requests\AulaRequest;
use App\Models\Aula;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AulaController extends Controller
{
    public function index(): View
    {
        return view('aule.index', [
            'aule' => Aula::query()->with('sede')->orderBy('nome')->get(),
        ]);
    }

    public function create(): View
    {
        return view('aule.create', [
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'tipiSuggeriti' => $this->tipiSuggeriti(),
        ]);
    }

    public function store(AulaRequest $request): RedirectResponse
    {
        Aula::query()->create($request->validated());

        return redirect()->route('aule.index')->with('successo', 'Aula creata.');
    }

    public function edit(Aula $aula): View
    {
        return view('aule.edit', [
            'aula' => $aula,
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'tipiSuggeriti' => $this->tipiSuggeriti(),
        ]);
    }

    public function update(AulaRequest $request, Aula $aula): RedirectResponse
    {
        $aula->update($request->validated());

        return redirect()->route('aule.index')->with('successo', 'Aula aggiornata.');
    }

    public function destroy(Aula $aula): RedirectResponse
    {
        $aula->delete();

        return redirect()->route('aule.index')->with('successo', 'Aula eliminata.');
    }

    /** Tipi base più quelli già in uso (es. tipi DADA creati da questa scuola). */
    private function tipiSuggeriti(): array
    {
        $usati = Aula::query()->distinct()->orderBy('tipo')->pluck('tipo')->all();

        return array_values(array_unique([...Aula::TIPI_BASE, ...$usati]));
    }
}
