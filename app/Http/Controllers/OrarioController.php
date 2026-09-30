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
        return view('orari.classe', [
            'orario' => $orario,
            'classe' => $classe,
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
            'lezioni' => $this->lezioniPerSlot($orario, $classe),
            'slotAttiviIds' => $classe->slotAttivi()->pluck('slot.id'),
            'cattedre' => Cattedra::query()->where('classe_id', $classe->id)->with('docente', 'disciplina')->get(),
            'avvisi' => $orario->avvisi,
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
            'slotPerGiorno' => Slot::query()->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno'),
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
