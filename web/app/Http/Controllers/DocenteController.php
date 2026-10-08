<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocenteRequest;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Sede;
use App\Models\Classe;
use App\Models\Slot;
use App\Services\SincronizzaRighe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            ->with('sospensioni')
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
            'docente' => $docente->load('classiConcorso', 'sedi', 'indisponibilita', 'sospensioni.supplenti'),
            'sospensioni' => $docente->sospensioni->map(fn ($s) => [
                'id' => $s->id, 'dal' => $s->dal->format('Y-m-d'), 'al' => $s->al?->format('Y-m-d'),
                'motivo' => $s->motivo, 'esclude_da_orario' => $s->esclude_da_orario, 'note' => $s->note,
                'supplenti' => $s->supplenti->pluck('id')->all(),
            ])->all(),
            'docentiSupplenti' => Docente::query()->whereKeyNot($docente->id)->orderBy('cognome')->orderBy('nome')->get(),
            'sedi' => Sede::query()->orderBy('nome')->get(),
            'classiConcorso' => $this->classiConcorso(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
            'cattedre' => $docente->cattedre()->with('classe')->get()->sortBy(fn ($c) => $c->classe->nomeCompleto())
                ->map(fn ($c) => $c->only(['id', 'classe_id', 'disciplina_id', 'ore', 'compresenza']))->all(),
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
            'indisponibiliIds' => $docente->indisponibilita()->pluck('slot.id'),
        ]);
    }

    public function update(DocenteRequest $request, Docente $docente): RedirectResponse
    {
        $cattedre = $request->input('cattedre', []);
        if ($request->boolean('cattedre_inviate')) {
            Gate::authorize('gestisci-anagrafica');
            SincronizzaRighe::controllaUnivoche($cattedre, ['classe_id', 'disciplina_id'], 'cattedre', 'Cattedra duplicata: stessa classe e disciplina.');
        }

        DB::transaction(function () use ($request, $docente, $cattedre) {
            $this->salva($docente, $request);

            if ($request->boolean('sezioni_extra')) {
                $docente->indisponibilita()->sync($request->input('slot_ids', []));
            }
            if ($request->boolean('sospensioni_inviate')) {
                $righe = array_map(fn ($r) => ['al' => $r['al'] ?: null, 'note' => $r['note'] ?: null] + $r, $request->input('sospensioni', []));
                SincronizzaRighe::applica($docente->sospensioni(), $righe, ['dal', 'al', 'motivo', 'esclude_da_orario', 'note'],
                    fn ($sospensione, $riga) => $sospensione->supplenti()->sync($riga['supplenti'] ?? []));
            }
            if ($request->boolean('cattedre_inviate')) {
                SincronizzaRighe::applica($docente->cattedre(), $cattedre, ['classe_id', 'disciplina_id', 'ore', 'compresenza']);
            }
        });

        return redirect()->route('docenti.edit', $docente)->with('successo', 'Docente aggiornato.');
    }

    public function destroy(Docente $docente): RedirectResponse
    {
        $docente->delete();

        return redirect()->route('docenti.index')->with('successo', 'Docente eliminato.');
    }

    /** Classi di concorso censite nelle discipline, con i nomi delle discipline che ciascuna abilita (codice => nomi). */
    private function classiConcorso(): array
    {
        return Disciplina::query()->whereNotNull('classe_concorso')->orderBy('nome')->get()
            ->groupBy('classe_concorso')->map(fn ($discipline) => $discipline->pluck('nome')->all())->all();
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
}
