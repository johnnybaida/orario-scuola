<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaboratorioRequest;
use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Laboratorio;
use App\Models\Lezione;
use App\Models\Slot;
use App\Services\Laboratori;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Laboratori pomeridiani: assegnati a mano, fuori dal monte ore e dalla generazione, ma occupano docenti e aule. */
class LaboratorioController extends Controller
{
    public function index(Laboratori $servizio): View
    {
        $orario = $servizio->orarioDiRiferimento();
        $lezioni = $orario ? Lezione::query()->where('orario_id', $orario->id)->with('cattedra.classe.aulaBase', 'cattedra.docente', 'cattedra.disciplina', 'aula', 'slot')->get() : collect();

        return view('laboratori.index', [
            'laboratori' => Laboratorio::query()->with('docenti', 'classi', 'slot', 'aula')->orderBy('nome')->get(),
            'conflitti' => array_column($servizio->conflitti($lezioni), 'testo'),
        ]);
    }

    public function create(): View
    {
        return view('laboratori.create', $this->opzioniForm());
    }

    public function store(LaboratorioRequest $request): RedirectResponse
    {
        DB::transaction(fn () => $this->salva(new Laboratorio, $request));

        return redirect()->route('laboratori.index')->with('successo', 'Laboratorio creato.');
    }

    public function edit(Laboratorio $laboratorio): View
    {
        return view('laboratori.edit', $this->opzioniForm() + ['laboratorio' => $laboratorio->load('docenti', 'classi', 'slot')]);
    }

    public function update(LaboratorioRequest $request, Laboratorio $laboratorio): RedirectResponse
    {
        DB::transaction(fn () => $this->salva($laboratorio, $request));

        return redirect()->route('laboratori.index')->with('successo', 'Laboratorio aggiornato.');
    }

    public function destroy(Laboratorio $laboratorio): RedirectResponse
    {
        $laboratorio->delete();

        return redirect()->route('laboratori.index')->with('successo', 'Laboratorio eliminato.');
    }

    /** Per ogni ora pomeridiana, perché docenti e aula scelti nel form non sono liberi (usato da laboratori.js). */
    public function disponibilita(Request $request, Laboratori $servizio): JsonResponse
    {
        $dati = $request->validate(['docenti' => ['nullable', 'array'], 'docenti.*' => ['integer'], 'aula_id' => ['nullable', 'integer'], 'escludi' => ['nullable', 'integer']]);

        return response()->json($servizio->disponibilita($dati['docenti'] ?? [], $dati['aula_id'] ?? null, $dati['escludi'] ?? null));
    }

    private function salva(Laboratorio $laboratorio, LaboratorioRequest $request): void
    {
        $laboratorio->fill(['attivo' => $request->boolean('attivo')] + $request->safe()->only(['nome', 'aula_id', 'n_partecipanti', 'note']))->save();
        $laboratorio->docenti()->sync($request->input('docenti', []));
        $laboratorio->classi()->sync($request->input('classi', []));
        $laboratorio->slot()->sync($request->input('slot_ids', []));
    }

    private function opzioniForm(): array
    {
        return [
            'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get(),
            'aule' => Aula::query()->orderBy('nome')->get(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'slotPerGiorno' => Slot::query()->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA)->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
        ];
    }
}
