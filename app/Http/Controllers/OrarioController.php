<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cattedra;
use App\Models\CompresenzaSostegno;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Services\Editor\EditorLezione;
use App\Support\StatiOrario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrarioController extends Controller
{
    public function index(): View
    {
        return view('orari.index', [
            'orari' => Orario::query()->with('periodo')->orderByDesc('id')->get(),
            'classi' => Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get(),
            'docenti' => Docente::query()->orderBy('cognome')->get(),
        ]);
    }

    public function classe(Orario $orario, Classe $classe): View
    {
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
            'lezioni' => $this->lezioniPerSlot($orario, $classe),
            'slotAttiviIds' => $classe->slotAttivi()->pluck('slot.id'),
            // In ordine alfabetico per materia (poi docente): più facili da trovare nella select.
            'cattedre' => Cattedra::query()->where('classe_id', $classe->id)->with('docente', 'disciplina')->get()
                ->sortBy(fn (Cattedra $c) => mb_strtolower($c->disciplina->nome.'|'.$c->docente->nomeCompleto()), SORT_NATURAL)->values(),
            'avvisi' => $orario->avvisi,
            'modificabile' => $orario->modificabile() && (bool) request()->user()?->can('gestisci-anagrafica'),
            'compresenze' => $compresenze,
        ]);
    }

    public function docente(Orario $orario, Docente $docente): View
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
        ]);
    }

    public function spostaLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);

        $dati = $request->validate(['slot_id' => ['required', 'integer', 'exists:slot,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->esegui($lezione, $dati['slot_id'], $request->user()->id);

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    public function cambiaCattedraLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $this->soloBozza($orario);

        $dati = $request->validate(['cattedra_id' => ['required', 'integer', 'exists:cattedre,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->cambiaCattedra($lezione, $dati['cattedra_id'], $request->user()->id);

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    public function bloccaLezione(Request $request, Orario $orario, Lezione $lezione): JsonResponse
    {
        $this->soloBozza($orario);
        abort_if($lezione->orario_id !== $orario->id, 404);

        $prima = $lezione->bloccata;
        $lezione->update(['bloccata' => ! $prima]);

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'entita' => 'Lezione',
            'entita_id' => $lezione->id,
            'azione' => 'blocco',
            'dati_prima' => ['bloccata' => $prima],
            'dati_dopo' => ['bloccata' => ! $prima],
        ]);

        return response()->json(['ok' => true, 'bloccata' => $lezione->bloccata]);
    }

    public function annullaUltima(Orario $orario, EditorLezione $servizio): RedirectResponse
    {
        $this->soloBozza($orario);
        $annullato = $servizio->annullaUltima($orario);

        return back()->with($annullato ? 'successo' : 'errore', $annullato
            ? 'Ultima modifica annullata.'
            : 'Nessuna modifica da annullare.');
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
    public function duplica(Request $request, Orario $orario): RedirectResponse
    {
        $copia = DB::transaction(function () use ($request, $orario) {
            $copia = Orario::query()->create([
                'periodo_id' => $orario->periodo_id,
                'versione' => (Orario::query()->where('periodo_id', $orario->periodo_id)->max('versione') ?? 0) + 1,
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

        return redirect()->route('orari.index')->with('successo', "Orario duplicato: versione {$copia->versione} in bozza.");
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
            ->with('cattedra.disciplina', 'cattedra.docente', 'aula')
            ->get()
            ->keyBy('slot_id');
    }
}
