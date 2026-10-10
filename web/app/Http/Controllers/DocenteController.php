<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocenteRequest;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Classe;
use App\Models\Slot;
use App\Services\AssistenzaPause;
use App\Services\SincronizzaRighe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocenteController extends Controller
{
    /** Filtri sulle ore: tutti | non_corrette (assegnate ≠ dovute) | in_piu (oltre le dovute) | mancanti (meno delle dovute). */
    public const FILTRI_ORE = ['non_corrette' => 'Ore non corrette', 'in_piu' => 'Ore oltre le dovute', 'mancanti' => 'Ore mancanti'];

    public function index(Request $request): View
    {
        $assistenza = app(AssistenzaPause::class);
        $filtroOre = array_key_exists($request->string('ore')->toString(), self::FILTRI_ORE) ? $request->string('ore')->toString() : '';

        $query = Docente::query()
            ->when($request->string('cerca')->toString(), function ($query, $cerca) {
                $query->where(function ($q) use ($cerca) {
                    $q->where('nome', 'like', "%{$cerca}%")->orWhere('cognome', 'like', "%{$cerca}%");
                });
            })
            ->with('sospensioni', 'assistenzePausa')
            ->withCount('cattedre')->withSum('cattedre', 'ore')->withSum('cattedreClil', 'ore_clil')->withSum('assegnazioniSostegno', 'ore')
            ->orderBy('cognome')->orderBy('nome');

        if ($filtroOre === '') {
            $docenti = $query->paginate(30)->withQueryString();
        } else {
            // Le ore assegnate si calcolano per docente (assistenze comprese): si filtra sull'insieme e poi si impagina.
            $filtrati = $query->get()->filter(function (Docente $d) use ($assistenza, $filtroOre) {
                $differenza = round($d->oreAssegnate($assistenza) - $d->ore_dovute, 2);

                return match ($filtroOre) {
                    'in_piu' => $differenza > 0,
                    'mancanti' => $differenza < 0,
                    default => $differenza !== 0.0,
                };
            })->values();
            $pagina = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $docenti = new \Illuminate\Pagination\LengthAwarePaginator($filtrati->forPage($pagina, 30)->values(), $filtrati->count(), 30, $pagina, ['path' => $request->url(), 'query' => $request->query()]);
        }

        return view('docenti.index', [
            'assistenza' => $assistenza, 'docenti' => $docenti, 'cerca' => $request->string('cerca')->toString(),
            'filtroOre' => $filtroOre, 'filtriOre' => self::FILTRI_ORE,
        ]);
    }

    public function create(): View
    {
        return view('docenti.create', ['classiConcorso' => $this->classiConcorso()]);
    }

    public function store(DocenteRequest $request): RedirectResponse
    {
        $docente = $this->salva(new Docente, $request);

        return redirect()->route('docenti.edit', $docente)->with('successo', 'Docente creato.');
    }

    public function edit(Docente $docente, AssistenzaPause $assistenza): View
    {
        $docente->load('assistenzePausa');
        $indisponibili = $docente->indisponibilita()->pluck('slot.id');
        return view('docenti.edit', [
            'docente' => $docente->load('classiConcorso', 'indisponibilita', 'sospensioni.supplenti'),
            'sospensioni' => $docente->sospensioni->map(fn ($s) => [
                'id' => $s->id, 'dal' => $s->dal->format('Y-m-d'), 'al' => $s->al?->format('Y-m-d'),
                'motivo' => $s->motivo, 'esclude_da_orario' => $s->esclude_da_orario, 'note' => $s->note,
                'supplenti' => $s->supplenti->pluck('id')->all(),
            ])->all(),
            'pause' => $assistenza->pause(),
            'assistenze' => $docente->assistenzePausa->map(fn ($a) => [
                'id' => $a->id, 'giorno' => $a->giorno, 'ordine' => $a->ordine,
                'avviso' => ! $assistenza->pause()->has($a->ordine) ? 'Questa pausa non c\'è più nella scansione oraria.'
                    : (Slot::query()->where('giorno', $a->giorno)->whereNotIn('id', $indisponibili)->doesntExist() ? 'Il docente è indisponibile tutto il giorno.' : null),
            ])->all(),
            'docentiSupplenti' => Docente::query()->whereKeyNot($docente->id)->orderBy('cognome')->orderBy('nome')->get(),
            'classiConcorso' => $this->classiConcorso(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
            'cattedre' => $docente->cattedre()->with('classe')->get()->sortBy(fn ($c) => $c->classe->nomeCompleto())
                ->map(fn ($c) => $c->only(['id', 'classe_id', 'disciplina_id', 'ore', 'compresenza']))->all(),
            // Ore assegnate in altri modi (sola lettura qui): sostegno nella scheda della classe, compresenza CLIL nella cattedra.
            'sostegno' => $docente->assegnazioniSostegno()->with('classe')->get()->sortBy(fn ($a) => $a->classe->nomeCompleto())->values(),
            'clil' => $docente->cattedreClil()->with('classe', 'disciplina', 'docente')->where('ore_clil', '>', 0)->get()->sortBy(fn ($c) => $c->classe->nomeCompleto())->values(),
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
            'indisponibiliIds' => $docente->indisponibilita()->pluck('slot.id'),
        ]);
    }

    public function update(DocenteRequest $request, Docente $docente): RedirectResponse
    {
        $assistenze = $request->input('assistenze', []);
        if ($request->boolean('assistenze_inviate')) {
            Gate::authorize('gestisci-anagrafica');
            SincronizzaRighe::controllaUnivoche($assistenze, ['giorno', 'ordine'], 'assistenze', 'Assistenza duplicata: stesso giorno e stessa pausa.');
        }

        $cattedre = $request->input('cattedre', []);
        if ($request->boolean('cattedre_inviate')) {
            Gate::authorize('gestisci-anagrafica');
            SincronizzaRighe::controllaUnivoche($cattedre, ['classe_id', 'disciplina_id'], 'cattedre', 'Cattedra duplicata: stessa classe e disciplina.');
        }

        DB::transaction(function () use ($request, $docente, $cattedre, $assistenze) {
            $this->salva($docente, $request);

            if ($request->boolean('sezioni_extra')) {
                $docente->indisponibilita()->sync($request->input('slot_ids', []));
            }
            if ($request->boolean('sospensioni_inviate')) {
                $righe = array_map(fn ($r) => ['al' => $r['al'] ?: null, 'note' => $r['note'] ?: null] + $r, $request->input('sospensioni', []));
                SincronizzaRighe::applica($docente->sospensioni(), $righe, ['dal', 'al', 'motivo', 'esclude_da_orario', 'note'],
                    fn ($sospensione, $riga) => $sospensione->supplenti()->sync($riga['supplenti'] ?? []));
            }
            if ($request->boolean('assistenze_inviate')) {
                SincronizzaRighe::applica($docente->assistenzePausa(), $assistenze, ['giorno', 'ordine']);
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

        return $docente;
    }
}
