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
            'tipiAula' => Aula::tipiConAule(),
            'pause' => app(\App\Services\AssistenzaPause::class)->pause(),
        ]);
    }

    public function store(DisciplinaRequest $request): RedirectResponse
    {
        Disciplina::query()->create($this->dati($request));

        return redirect()->route('discipline.index')->with('successo', 'Disciplina creata.');
    }

    public function edit(Disciplina $disciplina): View
    {
        return view('discipline.edit', [
            'disciplina' => $disciplina,
            'discipline' => Disciplina::query()->where('id', '!=', $disciplina->id)->orderBy('nome')->get(),
            'tipiAula' => Aula::tipiConAule(),
            'pause' => app(\App\Services\AssistenzaPause::class)->pause(),
        ]);
    }

    public function update(DisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $disciplina->update($this->dati($request));

        return redirect()->route('discipline.index')->with('successo', 'Disciplina aggiornata.');
    }

    public function destroy(Disciplina $disciplina): RedirectResponse
    {
        $disciplina->delete();

        return redirect()->route('discipline.index')->with('successo', 'Disciplina eliminata.');
    }

    /** Gli altri tipi ammessi non ripetono quello richiesto e senza altri tipi il campo resta vuoto. */
    private function dati(DisciplinaRequest $request): array
    {
        $dati = $request->validated();
        $extra = array_values(array_diff(array_unique($dati['tipi_aula_extra'] ?? []), [$dati['tipo_aula_richiesto'] ?? null]));
        $dati['tipi_aula_extra'] = $extra ?: null;
        $dati['senza_slot'] = $request->boolean('senza_slot');
        $dati['pausa_dopo_ora'] = $dati['senza_slot'] && ($dati['pausa_dopo_ora'] ?? '') !== '' ? (int) $dati['pausa_dopo_ora'] : null;   // la pausa vale solo per le discipline senza ora

        return $dati;
    }
}
