<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClasseRequest;
use App\Models\Aula;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Services\SincronizzaRighe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClasseController extends Controller
{
    public function index(): View
    {
        return view('classi.index', [
            'classi' => Classe::query()->with('sede', 'quadroOrario')->withCount('cattedre')
                ->orderBy('anno_corso')->orderBy('sezione')->get(),
        ]);
    }

    public function create(): View
    {
        return view('classi.create', $this->opzioniForm());
    }

    public function store(ClasseRequest $request): RedirectResponse
    {
        $classe = Classe::query()->create($request->safe()->except('rientri'));
        $this->applicaSlotDefault($classe);
        $this->sincronizzaRientri($classe, $request->input('rientri', []));

        return redirect()->route('classi.edit', $classe)->with('successo', 'Classe creata.');
    }

    public function edit(Classe $classe): View
    {
        return view('classi.edit', array_merge($this->opzioniForm(), [
            'classe' => $classe,
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
            'slotAttiviIds' => $classe->slotAttivi()->pluck('slot.id'),
            'rientriAttivi' => $classe->slotAttivi()->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA)->pluck('giorno')->unique()->values()->all(),
            'fabbisogni' => $classe->fabbisogniSostegno()->orderBy('codice_anonimo')->get()
                ->map(fn ($f) => $f->only(['id', 'codice_anonimo', 'ore_settimanali', 'docente_unico']))->all(),
            'assegnazioni' => $classe->assegnazioniSostegno()->get()->map(fn ($a) => $a->only(['id', 'docente_id', 'ore']))->all(),
            'cattedre' => $classe->cattedre()->with('disciplina')->get()->sortBy('disciplina.nome')
                ->map(fn ($c) => $c->only(['id', 'docente_id', 'disciplina_id', 'ore', 'compresenza']))->all(),
            'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
            'docentiSostegno' => Docente::query()->where('tipo_posto', 'sostegno')->orderBy('cognome')->get(),
        ]));
    }

    public function update(ClasseRequest $request, Classe $classe): RedirectResponse
    {
        $cattedre = $request->input('cattedre', []);
        if ($request->boolean('cattedre_inviate')) {
            Gate::authorize('gestisci-anagrafica');
            SincronizzaRighe::controllaUnivoche($cattedre, ['docente_id', 'disciplina_id'], 'cattedre', 'Cattedra duplicata: stesso docente e disciplina.');
        }

        DB::transaction(function () use ($request, $classe, $cattedre) {
            $classe->update($request->safe()->only(['anno_corso', 'sezione', 'sede_id', 'aula_base_id', 'quadro_orario_id', 'tempo_scuola', 'n_alunni']));

            if ($request->boolean('sezioni_extra')) {
                // La griglia degli slot è completa e prevale sui giorni di rientro (che servono solo a spuntarla).
                $classe->slotAttivi()->sync($request->input('slot_ids', []));
                $classe->update(['conteggio_sostegno' => $request->input('conteggio_sostegno') ?: null]);
                SincronizzaRighe::applica($classe->fabbisogniSostegno(), $request->input('fabbisogni', []), ['codice_anonimo', 'ore_settimanali', 'docente_unico']);
                SincronizzaRighe::applica($classe->assegnazioniSostegno(), $request->input('assegnazioni', []), ['docente_id', 'ore']);
            } else {
                $this->sincronizzaRientri($classe, $request->input('rientri', []));
            }
            if ($request->boolean('cattedre_inviate')) {
                SincronizzaRighe::applica($classe->cattedre(), $cattedre, ['docente_id', 'disciplina_id', 'ore', 'compresenza']);
            }
        });

        return redirect()->route('classi.edit', $classe)->with('successo', 'Classe aggiornata.');
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        $classe->delete();

        return redirect()->route('classi.index')->with('successo', 'Classe eliminata.');
    }

    private function opzioniForm(): array
    {
        return [
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'aule' => Aula::query()->where('tipo', 'classe')->orderBy('nome')->get(),
            'quadri' => QuadroOrario::query()->orderBy('nome')->get(),
            // Giorni in cui la scansione di istituto prevede ore pomeridiane.
            'giorniRientro' => Slot::query()->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA)->distinct()->orderBy('giorno')->pluck('giorno'),
        ];
    }

    /** Attiva le ore pomeridiane dei soli giorni scelti; le ore mattutine restano come sono. */
    private function sincronizzaRientri(Classe $classe, array $giorni): void
    {
        $pomeridiani = Slot::query()->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA)->get();
        $classe->slotAttivi()->detach($pomeridiani->pluck('id'));
        $classe->slotAttivi()->attach($pomeridiani->whereIn('giorno', array_map('intval', $giorni))->pluck('id'));
    }

    /** Alla creazione, attiva di default tutti gli slot mattutini (tempo normale). */
    private function applicaSlotDefault(Classe $classe): void
    {
        $slotIds = Slot::query()->where('ordine', '<=', Slot::ULTIMA_ORA_MATTINA)->pluck('id');
        $classe->slotAttivi()->sync($slotIds);
    }

    public function importForm(): View
    {
        return view('classi.import', $this->opzioniForm());
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $righe = 0;
        $errori = [];
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $intestazione = fgetcsv($handle, escape: '\\');

        DB::transaction(function () use ($handle, $intestazione, &$righe, &$errori) {
            $numeroRiga = 1;
            while (($valori = fgetcsv($handle, escape: '\\')) !== false) {
                $numeroRiga++;
                $riga = array_combine($intestazione, $valori);

                $sedeId = Sede::query()->where('nome', $riga['sede'] ?? '')->value('id') ?? Sede::query()->value('id');
                $quadroId = QuadroOrario::query()->where('nome', $riga['quadro_orario'] ?? '')->value('id') ?? QuadroOrario::query()->value('id');

                if (empty($riga['anno_corso']) || empty($riga['sezione']) || ! $sedeId || ! $quadroId) {
                    $errori[] = "Riga {$numeroRiga}: dati incompleti o sede/quadro orario non trovati.";

                    continue;
                }

                $classe = Classe::query()->create([
                    'anno_corso' => $riga['anno_corso'],
                    'sezione' => $riga['sezione'],
                    'sede_id' => $sedeId,
                    'quadro_orario_id' => $quadroId,
                    'tempo_scuola' => $riga['tempo_scuola'] ?? 'normale',
                    'n_alunni' => $riga['n_alunni'] ?? 0,
                ]);
                $this->applicaSlotDefault($classe);

                $righe++;
            }
        });

        fclose($handle);

        return redirect()->route('classi.index')
            ->with('successo', "Importate {$righe} classi.")
            ->with('errori_import', $errori);
    }
}
