<?php

namespace App\Http\Controllers;

use App\Constraints\Catalogo;
use App\Http\Requests\VincoloRequest;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Slot;
use App\Models\Vincolo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VincoloController extends Controller
{
    public function index(Request $request): View
    {
        $vincoli = Vincolo::query()
            ->when($request->string('tipo')->toString(), fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->orderByDesc('id')
            ->get();

        return view('vincoli.index', [
            'vincoli' => $vincoli,
            'etichette' => Catalogo::etichette(),
            'discipline' => Disciplina::query()->pluck('nome', 'id'),
            'filtroTipo' => $request->string('tipo')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('vincoli.create', $this->opzioniForm());
    }

    public function store(VincoloRequest $request): RedirectResponse
    {
        Vincolo::query()->create($this->datiPuliti($request));

        return redirect()->route('vincoli.index')->with('successo', 'Vincolo creato.');
    }

    public function edit(Vincolo $vincolo): View
    {
        return view('vincoli.edit', array_merge($this->opzioniForm(), ['vincolo' => $vincolo]));
    }

    public function update(VincoloRequest $request, Vincolo $vincolo): RedirectResponse
    {
        $vincolo->update($this->datiPuliti($request));

        return redirect()->route('vincoli.index')->with('successo', 'Vincolo aggiornato.');
    }

    public function destroy(Vincolo $vincolo): RedirectResponse
    {
        $vincolo->delete();

        return redirect()->route('vincoli.index')->with('successo', 'Vincolo eliminato.');
    }

    private function datiPuliti(VincoloRequest $request): array
    {
        $dati = $request->validated();

        return [
            'tipo' => $dati['tipo'],
            'ambito_livello' => $dati['ambito_livello'],
            'ambito_ids' => $dati['ambito_ids'] ?? [],
            'parametri' => $dati['parametri'] ?? [],
            'severita' => $dati['severita'],
            'peso' => $dati['peso'] ?? null,
            'attivo' => $request->boolean('attivo', true),
            'nota' => $dati['nota'] ?? null,
        ];
    }

    private function opzioniForm(): array
    {
        return [
            'etichette' => Catalogo::etichette(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'docenti' => Docente::query()->orderBy('cognome')->get(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
            'slot' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
        ];
    }
}
