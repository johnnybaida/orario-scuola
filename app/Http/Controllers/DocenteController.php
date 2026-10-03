<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocenteRequest;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Sede;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocenteController extends Controller
{
    public function index(Request $request): View
    {
        $docenti = Docente::query()
            ->when($request->string('cerca')->toString(), function ($query, $cerca) {
                $query->where(function ($q) use ($cerca) {
                    $q->where('nome', 'like', "%{$cerca}%")->orWhere('cognome', 'like', "%{$cerca}%");
                });
            })
            ->withCount('cattedre')
            ->orderBy('cognome')
            ->paginate(30)
            ->withQueryString();

        return view('docenti.index', ['docenti' => $docenti, 'cerca' => $request->string('cerca')->toString()]);
    }

    public function create(): View
    {
        return view('docenti.create', ['sedi' => Sede::query()->orderBy('nome')->get(), 'classiConcorso' => $this->classiConcorso()]);
    }

    public function store(DocenteRequest $request): RedirectResponse
    {
        $docente = $this->salva(new Docente, $request);

        return redirect()->route('docenti.edit', $docente)->with('successo', 'Docente creato.');
    }

    public function edit(Docente $docente): View
    {
        return view('docenti.edit', [
            'docente' => $docente->load('classiConcorso', 'sedi', 'indisponibilita'),
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'classiConcorso' => $this->classiConcorso(),
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
            'indisponibiliIds' => $docente->indisponibilita()->pluck('slot.id'),
        ]);
    }

    public function update(DocenteRequest $request, Docente $docente): RedirectResponse
    {
        $this->salva($docente, $request);

        return redirect()->route('docenti.edit', $docente)->with('successo', 'Docente aggiornato.');
    }

    public function destroy(Docente $docente): RedirectResponse
    {
        $docente->delete();

        return redirect()->route('docenti.index')->with('successo', 'Docente eliminato.');
    }

    public function updateIndisponibilita(Request $request, Docente $docente): RedirectResponse
    {
        $slotIds = $request->input('slot_ids', []);
        $docente->indisponibilita()->sync($slotIds);

        return redirect()->route('docenti.edit', $docente)->with('successo', 'Indisponibilità aggiornate.');
    }

    /** Classi di concorso censite nelle discipline. */
    private function classiConcorso(): array
    {
        return Disciplina::query()->whereNotNull('classe_concorso')->distinct()->pluck('classe_concorso')->all();
    }

    private function salva(Docente $docente, DocenteRequest $request): Docente
    {
        $dati = $request->validated();
        $classiConcorso = $dati['classi_concorso'] ?? [];
        $sediIds = $dati['sedi'] ?? [];

        $docente->fill([
            'nome' => $dati['nome'],
            'cognome' => $dati['cognome'],
            'email' => $dati['email'] ?? null,
            'tipo_contratto' => $dati['tipo_contratto'],
            'tipo_posto' => $dati['tipo_posto'],
            'regime' => $dati['regime'],
            'ore_dovute' => $dati['ore_dovute'],
            'coe' => $request->boolean('coe'),
        ])->save();

        $docente->classiConcorso()->delete();
        foreach ($classiConcorso as $cc) {
            $docente->classiConcorso()->create(['classe_concorso' => $cc]);
        }

        $docente->sedi()->sync($sediIds);

        return $docente;
    }

    public function importForm(): View
    {
        return view('docenti.import');
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

                if (empty($riga['nome']) || empty($riga['cognome'])) {
                    $errori[] = "Riga {$numeroRiga}: nome e cognome sono obbligatori.";

                    continue;
                }

                Docente::query()->create([
                    'nome' => $riga['nome'],
                    'cognome' => $riga['cognome'],
                    'email' => $riga['email'] ?? null,
                    'tipo_contratto' => $riga['tipo_contratto'] ?? 'tempo_indeterminato',
                    'tipo_posto' => $riga['tipo_posto'] ?? 'comune',
                    'regime' => $riga['regime'] ?? 'tempo_pieno',
                    'ore_dovute' => $riga['ore_dovute'] ?? 18,
                ]);

                $righe++;
            }
        });

        fclose($handle);

        return redirect()->route('docenti.index')
            ->with('successo', "Importati {$righe} docenti.")
            ->with('errori_import', $errori);
    }
}
