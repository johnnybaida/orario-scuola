<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClasseRequest;
use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'fabbisogniSostegno' => $classe->fabbisogniSostegno()->orderBy('codice_anonimo')->get(),
            'assegnazioniSostegno' => $classe->assegnazioniSostegno()->with('docente')->get(),
            'docentiSostegno' => Docente::query()->where('tipo_posto', 'sostegno')->orderBy('cognome')->get(),
        ]));
    }

    public function update(ClasseRequest $request, Classe $classe): RedirectResponse
    {
        $classe->update($request->safe()->except('rientri'));
        $this->sincronizzaRientri($classe, $request->input('rientri', []));

        return redirect()->route('classi.edit', $classe)->with('successo', 'Classe aggiornata.');
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        $classe->delete();

        return redirect()->route('classi.index')->with('successo', 'Classe eliminata.');
    }

    public function updateSlotAttivi(Request $request, Classe $classe): RedirectResponse
    {
        $classe->slotAttivi()->sync($request->input('slot_ids', []));

        return redirect()->route('classi.edit', $classe)->with('successo', 'Slot attivi aggiornati.');
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
