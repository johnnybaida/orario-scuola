<?php

namespace App\Http\Controllers;

use App\Http\Requests\AulaRequest;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AulaController extends Controller
{
    public function index(): View
    {
        return view('aule.index', [
            'aule' => Aula::query()->with('sede')->orderBy('nome')->get(),
            'usataDa' => Disciplina::query()->whereNotNull('tipo_aula_richiesto')->get()
                ->groupBy('tipo_aula_richiesto')->map(fn ($d) => $d->pluck('nome')->implode(', ')),
        ]);
    }

    public function create(): View
    {
        return view('aule.create', [
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'tipiSuggeriti' => $this->tipiSuggeriti(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function store(AulaRequest $request): RedirectResponse
    {
        Aula::query()->create($this->dati($request));

        return redirect()->route('aule.index')->with('successo', 'Aula creata.');
    }

    public function edit(Aula $aula): View
    {
        return view('aule.edit', [
            'aula' => $aula,
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'tipiSuggeriti' => $this->tipiSuggeriti(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function update(AulaRequest $request, Aula $aula): RedirectResponse
    {
        $aula->update($this->dati($request));

        return redirect()->route('aule.index')->with('successo', 'Aula aggiornata.');
    }

    public function destroy(Aula $aula): RedirectResponse
    {
        $aula->delete();

        return redirect()->route('aule.index')->with('successo', 'Aula eliminata.');
    }

    /** Tipi base più quelli già in uso, esclusi i tipi DADA che la UI propone per disciplina. */
    private function tipiSuggeriti(): array
    {
        $dada = Disciplina::query()->where('tipo_aula_richiesto', 'like', 'dada\_%')->pluck('tipo_aula_richiesto')->all();
        $usati = Aula::query()->distinct()->orderBy('tipo')->pluck('tipo')->all();

        return array_values(array_diff(array_unique([...Aula::TIPI_BASE, ...$usati]), $dada));
    }

    /** Il valore "dada:{id}" della select crea/riusa il tipo "dada_{codice}" e lo collega alla disciplina. */
    private function dati(AulaRequest $request): array
    {
        $dati = $request->validated();
        if (str_starts_with($dati['tipo'], 'dada:')) {
            $disciplina = Disciplina::query()->findOrFail((int) substr($dati['tipo'], 5));
            $dati['tipo'] = 'dada_'.Str::slug($disciplina->codice, '_');
            $disciplina->update(['tipo_aula_richiesto' => $dati['tipo']]);
        }

        return $dati;
    }
}
