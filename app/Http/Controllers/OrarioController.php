<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\CompresenzaSostegno;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Services\Editor\ControlloOrario;
use App\Services\Editor\EditorLezione;
use App\Services\Editor\SpostamentiAula;
use App\Support\ColoriDiscipline;
use App\Support\StatiOrario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrarioController extends Controller
{
    public function index(ControlloOrario $controllo): View
    {
        $orari = Orario::query()->with('periodo', 'creatoDa')->orderByDesc('id')->get();

        return view('orari.index', [
            'orari' => $orari,
            // Errori e avvisi reali di ciascun orario (ricalcolati a ogni apertura della pagina).
            'conteggi' => $orari->mapWithKeys(function (Orario $o) use ($controllo) {
                $p = collect($controllo->problemi($o));

                return [$o->id => ['errori' => $p->where('gravita', 'errore')->count(), 'avvisi' => $p->where('gravita', 'avviso')->count()]];
            }),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'docenti' => Docente::query()->orderBy('cognome')->get(),
            'aule' => Aula::query()->orderBy('nome')->get(),
        ]);
    }

    /** Tutti i problemi reali dell'orario, con il link alla classe dove correggerli. */
    public function controllo(Orario $orario, ControlloOrario $controllo): View
    {
        return view('orari.controllo', [
            'orario' => $orario,
            'problemi' => $controllo->problemi($orario),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get()->keyBy('id'),
        ]);
    }

    public function classe(Orario $orario, Classe $classe, EditorLezione $servizio, ControlloOrario $controllo, SpostamentiAula $spostamenti): View
    {
        $lezioniClasse = $this->lezioniPerSlot($orario, $classe);
        $problemi = $controllo->perClasse($controllo->problemi($orario), $classe->id);
        $perLezione = [];
        foreach ($problemi as $problema) {
            foreach ($problema['lezioni'] as $id) {
                $perLezione[$id][] = $problema['testo'];
            }
        }

        $compresenze = $orario->compresenzeSostegno()
            ->where('classe_id', $classe->id)
            ->with('docente')
            ->get()
            ->groupBy('slot_id');

        return view('orari.classe', [
            'orario' => $orario,
            'classe' => $classe,
            // Solo fino all'ultima ora attiva della classe.
            'slotPerGiorno' => Slot::perGiorno($classe->slotAttivi()->max('ordine')),
            'lezioni' => $lezioniClasse,
            'cambiAula' => $spostamenti->cambi($lezioniClasse),
            'slotAttiviIds' => $classe->slotAttivi()->pluck('slot.id'),
            // In ordine alfabetico per materia (poi docente): più facili da trovare nella select.
            'cattedre' => Cattedra::query()->where('classe_id', $classe->id)->with('docente', 'disciplina')->get()
                ->sortBy(fn (Cattedra $c) => mb_strtolower($c->disciplina->nome.'|'.$c->docente->nomeCompleto()), SORT_NATURAL)->values(),
            'problemi' => $problemi,
            'problemiPerLezione' => $perLezione,
            'classiOrario' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get()->keyBy('id'),
            'avvisi' => $orario->avvisi,
            'lezioniInErrore' => $orario->avvisi->where('tipo', 'errore')->pluck('lezione_id')->filter()->unique()->all(),
            'puoAnnullare' => $servizio->puoAnnullare($orario),
            'puoRipetere' => $servizio->puoRipetere($orario),
            'modificabile' => $orario->modificabile() && (bool) request()->user()?->can('gestisci-anagrafica'),
            'compresenze' => $compresenze,
        ]);
    }

    public function docente(Orario $orario, Docente $docente, ControlloOrario $controllo): View
    {
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->whereHas('cattedra', fn ($q) => $q->where('docente_id', $docente->id))
            ->with('cattedra.classe', 'cattedra.disciplina', 'aula')
            ->get()
            ->keyBy('slot_id');

        return view('orari.docente', [
            'orario' => $orario,
            'docente' => $docente,
            // Solo fino all'ultima ora in cui il docente ha lezione.
            'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $lezioni->keys())->max('ordine')),
            'lezioni' => $lezioni,
            'problemi' => $controllo->perDocente($controllo->problemi($orario), $docente->id),
            'classiOrario' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get()->keyBy('id'),
        ]);
    }

    /**
     * Tabellone a schermo: tutte le classi (o tutte le aule) per tutte le ore, con un colore per disciplina e gli
     * spostamenti d'aula. Nella vista per aula, con l'orario in bozza, le lezioni si trascinano tra aule e ore.
     */
    public function tabellone(Request $request, Orario $orario, ControlloOrario $controllo, SpostamentiAula $spostamenti): View
    {
        $classi = Classe::query()->with('aulaBase')->orderBy('anno_corso')->orderBy('sezione')->get();
        // Senza indicazione: «per aula» se la scuola lavora (in prevalenza) senza aule base, cioè in DADA.
        $per = $request->query('per') ?? ($classi->isNotEmpty() && $classi->whereNull('aula_base_id')->count() * 2 > $classi->count() ? 'aula' : 'classe');
        abort_unless(in_array($per, ['classe', 'aula'], true), 404);

        $lezioni = Lezione::query()->where('orario_id', $orario->id)
            ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'slot', 'aula')->get();
        $usati = $lezioni->pluck('slot')->unique('id');
        $problemi = $controllo->problemi($orario);
        $perLezione = [];
        foreach ($problemi as $problema) {
            foreach ($problema['lezioni'] as $id) {
                $perLezione[$id][] = $problema['testo'];
            }
        }

        $cambi = [];
        $cambiPerClasse = [];
        foreach ($lezioni->groupBy(fn (Lezione $l) => $l->cattedra->classe_id) as $classeId => $sue) {
            $c = $spostamenti->cambi($sue);
            $cambi += $c;
            $cambiPerClasse[$classeId] = count($c);
        }

        if ($per === 'classe') {
            $righe = $classi->map(fn (Classe $c) => ['id' => $c->id, 'etichetta' => $c->nomeCompleto()]);
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id);
        } else {
            $aule = Aula::query()->orderBy('nome')->get();
            $tipiRichiesti = Cattedra::query()->with('disciplina')->get()->pluck('disciplina.tipo_aula_richiesto')->filter()->unique();
            $usate = $lezioni->map(fn (Lezione $l) => SpostamentiAula::aulaEffettiva($l)?->id)->filter()->unique();
            $righe = $aule->filter(fn (Aula $a) => $usate->contains($a->id) || $tipiRichiesti->contains($a->tipo))
                ->map(fn (Aula $a) => ['id' => $a->id, 'etichetta' => $a->nome])->values();
            if ($lezioni->contains(fn (Lezione $l) => ! SpostamentiAula::aulaEffettiva($l))) {
                $righe->push(['id' => 0, 'etichetta' => 'Senza aula']);
            }
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.(SpostamentiAula::aulaEffettiva($l)?->id ?? 0));
        }

        return view('orari.tabellone', [
            'orario' => $orario,
            'per' => $per,
            'righe' => $righe,
            'celle' => $celle,
            'giorni' => $usati->pluck('giorno')->unique()->sort()->values(),
            'ore' => $usati->isEmpty() ? [] : range(1, (int) $usati->max('ordine')),
            'slot' => Slot::query()->get()->keyBy(fn (Slot $s) => $s->giorno.'-'.$s->ordine),
            'colori' => ColoriDiscipline::mappa(),
            'discipline' => $lezioni->pluck('cattedra.disciplina')->unique('id')->sortBy('codice'),
            'cambi' => $cambi,
            'cambiPerClasse' => $cambiPerClasse,
            'problemiPerLezione' => $perLezione,
            'problemi' => $problemi,
            'classiOrario' => $classi->keyBy('id'),
            'avvisi' => $orario->avvisi,
            'modificabile' => $orario->modificabile() && (bool) $request->user()?->can('gestisci-anagrafica'),
        ]);
    }

    /** Occupazione di un'aula (anche quella base di una classe): chi c'è a ogni ora. Vista in sola lettura. */
    public function aula(Orario $orario, Aula $aula, ControlloOrario $controllo): View
    {
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->inAula($aula)
            ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente')
            ->get()
            ->groupBy('slot_id');

        return view('orari.aula', [
            'orario' => $orario,
            'aula' => $aula->load('sede'),
            // Solo fino all'ultima ora in cui l'aula è usata.
            'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $lezioni->keys())->max('ordine')),
            'lezioni' => $lezioni,
            'problemi' => $controllo->perLezioni($controllo->problemi($orario), $lezioni->flatten()->pluck('id')->all()),
            'classiOrario' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get()->keyBy('id'),
        ]);
    }

    public function spostaLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);

        $dati = $request->validate(['slot_id' => ['required', 'integer', 'exists:slot,id'], 'aula_id' => ['nullable', 'integer', 'exists:aule,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->esegui($lezione, $dati['slot_id'], $request->user()->id, $request->boolean('provvisorio'), $request->integer('aula_id') ?: null);

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    /** Dove si può mettere la lezione (ok / conflitto / vietato per ogni slot della classe): colora la griglia durante il trascinamento. */
    public function destinazioniLezione(Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);
        abort_if($lezione->orario_id !== $orario->id, 404);

        return response()->json($servizio->destinazioni($lezione));
    }

    public function cambiaAulaLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);

        $dati = $request->validate(['aula_id' => ['required', 'integer', 'exists:aule,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->cambiaAula($lezione, $dati['aula_id'], $request->user()->id, $request->boolean('provvisorio'));

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    /** Dove si può trascinare la lezione nel tabellone per aula (cella «{aula}-{slot}» → ok / conflitto / vietato). */
    public function destinazioniAuleLezione(Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);
        abort_if($lezione->orario_id !== $orario->id, 404);

        return response()->json($servizio->destinazioniAule($lezione));
    }

    public function cambiaCattedraLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);

        $dati = $request->validate(['cattedra_id' => ['required', 'integer', 'exists:cattedre,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->cambiaCattedra($lezione, $dati['cattedra_id'], $request->user()->id, $request->boolean('provvisorio'));

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    public function bloccaLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);
        abort_if($lezione->orario_id !== $orario->id, 404);

        return response()->json(['ok' => true, 'bloccata' => $servizio->blocca($lezione, $request->user()->id)]);
    }

    public function annullaUltima(Request $request, Orario $orario, EditorLezione $servizio): RedirectResponse
    {
        $this->soloBozza($orario);

        return $this->esito($servizio->annulla($orario, $request->user()->id));
    }

    public function ripeti(Request $request, Orario $orario, EditorLezione $servizio): RedirectResponse
    {
        $this->soloBozza($orario);

        return $this->esito($servizio->ripeti($orario, $request->user()->id));
    }

    /** @param  array{ok: bool, messaggio: string}  $esito */
    private function esito(array $esito): RedirectResponse
    {
        return $esito['ok'] ? back()->with('successo', $esito['messaggio']) : back()->withErrors(['annulla' => $esito['messaggio']]);
    }

    public function destroy(Request $request, Orario $orario): RedirectResponse
    {
        abort_unless(in_array($orario->stato, StatiOrario::ELIMINABILI, true), 422, 'Si eliminano solo gli orari in bozza o archiviati.');

        // Lezioni, avvisi e compresenze cadono in cascata; le generazioni restano come storico.
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'entita' => 'Orario',
            'entita_id' => $orario->id,
            'azione' => 'eliminazione',
            'dati_prima' => $orario->only(['periodo_id', 'versione', 'stato', 'seed', 'punteggio']),
            'dati_dopo' => null,
        ]);
        $orario->delete();

        return redirect()->route('orari.index')->with('successo', 'Orario eliminato.');
    }

    /** Copia di un orario (lezioni e compresenze di sostegno) come nuova versione in bozza, per provare varianti. */
    public function duplicaForm(Orario $orario): View
    {
        return view('orari.duplica', ['orario' => $orario]);
    }

    public function nomeForm(Orario $orario): View
    {
        return view('orari.nome', ['orario' => $orario]);
    }

    public function aggiornaNome(Request $request, Orario $orario): RedirectResponse
    {
        $request->validate(['nome' => ['nullable', 'string', 'max:120']]);
        $prima = $orario->nome;
        $orario->update(['nome' => $request->input('nome') ?: null]);

        AuditLog::registra('Orario', $orario->id, 'modifica', ['nome' => $prima], ['nome' => $orario->nome], $orario->etichetta());

        return redirect()->route('orari.index')->with('successo', 'Nome dell\'orario aggiornato.');
    }

    public function duplica(Request $request, Orario $orario): RedirectResponse
    {
        $request->validate(['nome' => ['nullable', 'string', 'max:120']]);

        $copia = DB::transaction(function () use ($request, $orario) {
            $copia = Orario::query()->create([
                'periodo_id' => $orario->periodo_id,
                'versione' => (Orario::query()->where('periodo_id', $orario->periodo_id)->max('versione') ?? 0) + 1,
                'nome' => $request->input('nome') ?: $orario->etichetta().' (copia)',
                'stato' => 'bozza',
                'seed' => $orario->seed,
                'punteggio' => $orario->punteggio,
                'creato_da' => $request->user()->id,
            ]);

            $adesso = now();
            foreach ($orario->lezioni()->get()->chunk(200) as $gruppo) {
                Lezione::query()->insert($gruppo->map(fn (Lezione $l) => [
                    'orario_id' => $copia->id, 'cattedra_id' => $l->cattedra_id, 'slot_id' => $l->slot_id,
                    'durata_slot' => $l->durata_slot, 'aula_id' => $l->aula_id, 'bloccata' => $l->bloccata,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ])->all());
            }
            foreach ($orario->compresenzeSostegno()->get()->chunk(200) as $gruppo) {
                CompresenzaSostegno::query()->insert($gruppo->map(fn (CompresenzaSostegno $c) => [
                    'orario_id' => $copia->id, 'docente_id' => $c->docente_id, 'classe_id' => $c->classe_id,
                    'slot_id' => $c->slot_id, 'codice_anonimo' => $c->codice_anonimo,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ])->all());
            }

            AuditLog::query()->create([
                'user_id' => $request->user()->id, 'entita' => 'Orario', 'entita_id' => $copia->id, 'azione' => 'duplicazione',
                'dati_prima' => ['orario_origine' => $orario->id], 'dati_dopo' => $copia->only(['periodo_id', 'versione', 'stato']),
            ]);

            return $copia;
        });

        return redirect()->route('orari.index')->with('successo', "Orario duplicato: «{$copia->nome}» in bozza.");
    }

    public function cambiaStato(Request $request, Orario $orario): RedirectResponse
    {
        $nuovo = $request->validate(['stato' => ['required', 'in:'.implode(',', array_keys(StatiOrario::ETICHETTE))]])['stato'];

        abort_unless(StatiOrario::transizioneValida($orario->stato, $nuovo), 422, 'Passaggio di stato non valido.');
        abort_unless(StatiOrario::puo($request->user(), $orario->stato, $nuovo), 403);

        DB::transaction(function () use ($request, $orario, $nuovo) {
            // Una sola versione pubblicata per periodo: la precedente passa in archivio.
            if ($nuovo === 'pubblicato') {
                Orario::query()->where('periodo_id', $orario->periodo_id)->where('stato', 'pubblicato')->where('id', '!=', $orario->id)
                    ->get()->each(function (Orario $precedente) use ($request) {
                        $this->registraStato($request, $precedente, 'archiviato');
                    });
            }
            $this->registraStato($request, $orario, $nuovo);
        });

        return redirect()->route('orari.index')->with('successo', "Orario versione {$orario->versione}: ".strtolower(StatiOrario::ETICHETTE[$nuovo]).'.');
    }

    private function registraStato(Request $request, Orario $orario, string $nuovo): void
    {
        $prima = $orario->stato;
        $orario->update(['stato' => $nuovo]);

        AuditLog::query()->create([
            'user_id' => $request->user()->id, 'entita' => 'Orario', 'entita_id' => $orario->id, 'azione' => 'cambio_stato',
            'dati_prima' => ['stato' => $prima], 'dati_dopo' => ['stato' => $nuovo],
        ]);
    }

    private function soloBozza(Orario $orario): void
    {
        abort_unless($orario->modificabile(), 422, "L'orario non è in bozza: duplicalo per modificarlo.");
    }

    public function azzeraAvvisi(Orario $orario): RedirectResponse
    {
        $this->soloBozza($orario);
        $orario->avvisi()->delete();

        return back()->with('successo', 'Avvisi azzerati.');
    }

    private function lezioniPerSlot(Orario $orario, Classe $classe)
    {
        return Lezione::query()
            ->where('orario_id', $orario->id)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'aula', 'slot')
            ->get()
            ->keyBy('slot_id');
    }
}
