<?php

namespace App\Services\Export;

use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

class OrarioPdfExporter
{
    public function classe(Orario $orario, Classe $classe): PdfDocument
    {
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->with('cattedra.disciplina', 'cattedra.docente', 'aula')
            ->get()
            ->keyBy('slot_id');

        return Pdf::loadView('orari.pdf.griglia', [
            'titolo' => "Orario classe {$classe->nomeCompleto()}",
            'slotPerGiorno' => Slot::perGiorno($classe->slotAttivi()->max('ordine')),
            'lezioni' => $lezioni,
            'colonna' => fn (Lezione $l) => $l->cattedra->disciplina->nome."\n".$l->cattedra->docente->nomeCompleto(),
        ])->setPaper('a4', 'landscape');
    }

    public function docente(Orario $orario, Docente $docente): PdfDocument
    {
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->whereHas('cattedra', fn ($q) => $q->where('docente_id', $docente->id))
            ->with('cattedra.classe', 'cattedra.disciplina', 'aula')
            ->get()
            ->keyBy('slot_id');

        return Pdf::loadView('orari.pdf.griglia', [
            'titolo' => "Orario docente {$docente->nomeCompleto()}",
            'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $lezioni->keys())->max('ordine')),
            'lezioni' => $lezioni,
            'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome,
        ])->setPaper('a4', 'landscape');
    }

    public function generale(Orario $orario): PdfDocument
    {
        $classi = Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get();
        $tutte = Lezione::query()
            ->where('orario_id', $orario->id)
            ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente')
            ->get();
        // Solo le ore in cui almeno una classe ha lezione (niente righe vuote, es. pomeriggi senza rientri).
        $slot = Slot::query()->whereIn('id', $tutte->pluck('slot_id'))->orderBy('giorno')->orderBy('ordine')->get();
        $lezioni = $tutte->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id);

        return Pdf::loadView('orari.pdf.tabellone', [
            'titolo' => 'Quadro generale orario',
            'classi' => $classi,
            'slot' => $slot,
            'lezioni' => $lezioni,
        ])->setPaper('a3', 'landscape');
    }
}
