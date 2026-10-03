<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Services\Editor\EditorLezione;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $dati = $request->validate(['slot_id' => ['required', 'integer', 'exists:slot,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->esegui($lezione, $dati['slot_id'], $request->user()->id);

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    public function cambiaCattedraLezione(Request $request, Orario $orario, Lezione $lezione, EditorLezione $servizio): JsonResponse
    {
        $dati = $request->validate(['cattedra_id' => ['required', 'integer', 'exists:cattedre,id']]);

        abort_if($lezione->orario_id !== $orario->id, 404);

        $risultato = $servizio->cambiaCattedra($lezione, $dati['cattedra_id'], $request->user()->id);

        return response()->json($risultato, $risultato['ok'] ? 200 : 422);
    }

    public function bloccaLezione(Request $request, Orario $orario, Lezione $lezione): JsonResponse
    {
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
        $annullato = $servizio->annullaUltima($orario);

        return back()->with($annullato ? 'successo' : 'errore', $annullato
            ? 'Ultima modifica annullata.'
            : 'Nessuna modifica da annullare.');
    }

    public function destroy(Request $request, Orario $orario): RedirectResponse
    {
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

    public function azzeraAvvisi(Orario $orario): RedirectResponse
    {
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
