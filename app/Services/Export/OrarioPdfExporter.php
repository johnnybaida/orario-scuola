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

    /**
     * Tabellone su un solo foglio: una riga per classe, colonne raggruppate per giorno con la stessa larghezza
     * per tutte le ore. Le ore vuote non compaiono; i docenti di sostegno in compresenza sono visibili in cella.
     */
    public function generale(Orario $orario): PdfDocument
    {
        $classi = Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get();
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->with('cattedra.disciplina', 'cattedra.docente')
            ->get();
        $compresenze = $orario->compresenzeSostegno()->with('docente')->get();

        // Giorni e ore realmente usati; ogni giorno ha tante colonne quante l'ora più alta usata in qualunque giorno.
        $usati = Slot::query()->whereIn('id', $lezioni->pluck('slot_id')->merge($compresenze->pluck('slot_id')))->get();
        $giorni = $usati->pluck('giorno')->unique()->sort()->values();
        $oreMax = (int) $usati->max('ordine');

        // Carattere il più grande possibile (6-10px) perché ~9 caratteri stiano in una colonna dell'A3 orizzontale;
        // materie e cognomi più lunghi vengono comunque troncati con "…".
        $larghezzaColonna = 1050 / max(1, $giorni->count() * $oreMax);
        $fontPx = max(6, min(10, (int) floor(($larghezzaColonna - 3) / 3.6)));
        $limite = max(4, (int) floor(($larghezzaColonna - 3) / (0.4 * $fontPx)));

        return Pdf::loadView('orari.pdf.tabellone', [
            'titolo' => 'Quadro generale orario',
            'classi' => $classi,
            'giorni' => $giorni,
            'ore' => $oreMax ? range(1, $oreMax) : [],
            'slot' => Slot::query()->get()->keyBy(fn (Slot $s) => $s->giorno.'-'.$s->ordine),
            'lezioni' => $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id),
            'sostegni' => $compresenze->groupBy(fn ($c) => $c->slot_id.'-'.$c->classe_id),
            'discipline' => $lezioni->pluck('cattedra.disciplina')->unique('id')->sortBy('codice'),
            'limite' => $limite,
            'fontPx' => $fontPx,
        ])->setPaper('a3', 'landscape');
    }
}
