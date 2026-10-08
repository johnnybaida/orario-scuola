<?php

namespace App\Http\Controllers;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\Orario;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Services\QueueWorker;
use App\Services\Validation\PreValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(PreValidator $preValidator, QueueWorker $worker): View
    {
        // Chi non può consultare le anagrafiche (es. il docente) vede solo il benvenuto.
        if (! Gate::allows('consulta')) {
            return view('dashboard', ['completa' => false]);
        }

        $conteggi = [
            'sedi' => Sede::query()->count(),
            'aule' => Aula::query()->count(),
            'discipline' => Disciplina::query()->count(),
            'quadri' => QuadroOrario::query()->count(),
            'docenti' => Docente::query()->count(),
            'classi' => Classe::query()->count(),
            'cattedre' => Cattedra::query()->count(),
        ];

        $ultimoOrario = Orario::query()->with('periodo')->latest('id')->first();

        return view('dashboard', [
            'completa' => true,
            'conteggi' => $conteggi,
            'tempoScuola' => Classe::query()->selectRaw('tempo_scuola, count(*) n')->groupBy('tempo_scuola')->pluck('n', 'tempo_scuola'),
            'tipiPosto' => Docente::query()->selectRaw('tipo_posto, count(*) n')->groupBy('tipo_posto')->pluck('n', 'tipo_posto'),
            'oreAssegnate' => (int) Cattedra::query()->sum('ore'),
            'oreDovute' => (int) Docente::query()->sum('ore_dovute'),
            'problemi' => $preValidator->problemi(),
            'percorso' => [
                ['Sedi', 'sedi.index', $conteggi['sedi']],
                ['Aule', 'aule.index', $conteggi['aule']],
                ['Discipline', 'discipline.index', $conteggi['discipline']],
                ['Quadri orari', 'quadri-orari.index', $conteggi['quadri']],
                ['Docenti', 'docenti.index', $conteggi['docenti']],
                ['Classi', 'classi.index', $conteggi['classi']],
                ['Cattedre', 'cattedre.index', $conteggi['cattedre']],
            ],
            'workerAttivo' => $worker->attivo() && ! $worker->inArresto(),
            'workerInArresto' => $worker->inArresto(),
            'ultimaGenerazione' => Generazione::query()->with('orario')->latest('id')->first(),
            'ultimoOrario' => $ultimoOrario,
            'avvisiAperti' => $ultimoOrario?->avvisi()->count() ?? 0,
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get(),
            'caricoDocenti' => $this->caricoDocenti(),
        ]);
    }

    /** Docenti con ore assegnate (cattedre + sostegno) diverse dalle ore dovute, i più sbilanciati per primi. */
    private function caricoDocenti()
    {
        $assistenza = app(\App\Services\AssistenzaPause::class);

        return Docente::query()->with('assistenzePausa')->withSum('cattedre', 'ore')->withSum('assegnazioniSostegno', 'ore')->get()
            ->map(fn (Docente $d) => [
                'docente' => $d,
                'assistenza' => $assistenza->minuti($d),
                'assegnate' => (int) $d->cattedre_sum_ore + (int) $d->assegnazioni_sostegno_sum_ore,
                'dovute' => $d->ore_dovute,
            ])
            ->map(fn (array $r) => $r + ['diff' => $r['assegnate'] - $r['dovute']])
            ->filter(fn (array $r) => $r['diff'] !== 0)
            ->sortByDesc(fn (array $r) => abs($r['diff']))
            ->values();
    }
}
