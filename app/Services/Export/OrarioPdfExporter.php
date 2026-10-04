<?php

namespace App\Services\Export;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

class OrarioPdfExporter
{
    public function classe(Orario $orario, Classe $classe): PdfDocument
    {
        return $this->classi($orario, collect([$classe]));
    }

    /**
     * Un foglio A4 orizzontale per classe, con il titolo centrato e tutta la settimana (anche i docenti di sostegno
     * in compresenza). Senza argomento: tutte le classi, in ordine.
     */
    public function classi(Orario $orario, ?Collection $classi = null): PdfDocument
    {
        $classi ??= Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get();
        $sostegni = $orario->compresenzeSostegno()->with('docente')->get()->groupBy('classe_id');

        $fogli = $classi->map(fn (Classe $classe) => [
            'titolo' => "Orario classe {$classe->nomeCompleto()}",
            'slotPerGiorno' => Slot::perGiorno($classe->slotAttivi()->max('ordine')),
            'lezioni' => Lezione::query()
                ->where('orario_id', $orario->id)
                ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
                ->with('cattedra.disciplina', 'cattedra.docente', 'aula')
                ->get()
                ->keyBy('slot_id'),
            'colonna' => fn (Lezione $l) => $l->cattedra->disciplina->nome."\n".$l->cattedra->docente->nomeCompleto().($l->aulaDaMostrare() ? "\n".$l->aulaDaMostrare()->nome : ''),
            'sostegni' => ($sostegni[$classe->id] ?? collect())->groupBy('slot_id')
                ->map(fn ($gruppo) => $gruppo->pluck('docente.cognome')->unique()->values()->all())->all(),
        ])->all();

        return Pdf::loadView('orari.pdf.griglia', ['fogli' => $fogli])->setPaper('a4', 'landscape');
    }

    public function docente(Orario $orario, Docente $docente): PdfDocument
    {
        return $this->docenti($orario, collect([$docente]));
    }

    /**
     * Un foglio A4 orizzontale per docente (titolo centrato, tutta la settimana, classe e disciplina in ogni ora; le ore
     * di sostegno in compresenza come «S classe»). Senza argomento: tutti i docenti che hanno almeno un'ora in questo
     * orario, in ordine alfabetico.
     */
    public function docenti(Orario $orario, ?Collection $docenti = null): PdfDocument
    {
        $lezioni = Lezione::query()->where('orario_id', $orario->id)
            ->with('cattedra.classe', 'cattedra.disciplina', 'aula')->get()->groupBy(fn (Lezione $l) => $l->cattedra->docente_id);
        $compresenze = $orario->compresenzeSostegno()->with('classe')->get()->groupBy('docente_id');

        $docenti ??= Docente::query()->whereIn('id', $lezioni->keys()->merge($compresenze->keys())->unique())
            ->orderBy('cognome')->orderBy('nome')->get();

        $fogli = $docenti->map(function (Docente $docente) use ($lezioni, $compresenze) {
            $sue = ($lezioni[$docente->id] ?? collect())->keyBy('slot_id');
            $sostegni = ($compresenze[$docente->id] ?? collect());

            return [
                'titolo' => "Orario docente {$docente->nomeCompleto()}",
                'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $sue->keys()->merge($sostegni->pluck('slot_id')))->max('ordine')),
                'lezioni' => $sue,
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome.($l->aulaDaMostrare() ? "\n".$l->aulaDaMostrare()->nome : ''),
                'sostegni' => $sostegni->groupBy('slot_id')->map(fn ($g) => $g->map(fn ($c) => $c->classe->nomeCompleto())->unique()->values()->all())->all(),
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['fogli' => $fogli])->setPaper('a4', 'landscape');
    }

    /**
     * Un foglio A4 orizzontale per aula (il foglio da appendere alla porta): per ogni ora la classe, la disciplina e il
     * docente. Comprende anche l'aula base delle classi. Senza argomento: tutte le aule usate in questo orario.
     */
    public function aule(Orario $orario, ?Collection $aule = null): PdfDocument
    {
        $lezioni = Lezione::query()->where('orario_id', $orario->id)
            ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'aula')->get();

        $aule ??= Aula::query()->orderBy('nome')->get()->filter(fn (Aula $a) => $lezioni->contains(fn (Lezione $l) => $this->inAula($l, $a)))->values();

        $fogli = $aule->map(function (Aula $aula) use ($lezioni) {
            $sue = $lezioni->filter(fn (Lezione $l) => $this->inAula($l, $aula))->groupBy('slot_id');

            return [
                'titolo' => "Orario aula {$aula->nome}",
                'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $sue->keys())->max('ordine')),
                'lezioni' => $sue,
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome."\n".$l->cattedra->docente->nomeCompleto(),
                'sostegni' => [],
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['fogli' => $fogli])->setPaper('a4', 'landscape');
    }

    /** Stessa regola di Lezione::scopeInAula, su lezioni già caricate. */
    private function inAula(Lezione $l, Aula $aula): bool
    {
        return $l->aula_id === $aula->id || ($l->aula_id === null && $l->cattedra->classe->aula_base_id === $aula->id);
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

        // Legenda: orario di ogni ora e ricreazioni (uguali per tutti i giorni: si leggono dal primo slot di ciascuna ora).
        $tuttiGliSlot = Slot::query()->orderBy('giorno')->orderBy('ordine')->get();
        $legendaOre = collect(range(1, max(1, $oreMax)))->map(function (int $ordine) use ($tuttiGliSlot) {
            $ora = $tuttiGliSlot->first(fn (Slot $s) => $s->ordine === $ordine);
            $prossima = $tuttiGliSlot->first(fn (Slot $s) => $s->ordine === $ordine + 1);

            return $ora ? [
                'ordine' => $ordine, 'inizio' => substr($ora->inizio, 0, 5), 'fine' => substr($ora->fine, 0, 5),
                'ricreazione' => $ora->fineRicreazione() && $prossima
                    ? ['fine' => $ora->fineRicreazione(), 'minuti' => $ora->ricreazione_minuti] : null,
            ] : null;
        })->filter()->values()->all();

        return Pdf::loadView('orari.pdf.tabellone', [
            'titolo' => 'Quadro generale orario',
            'classi' => $classi,
            'giorni' => $giorni,
            'ore' => $oreMax ? range(1, $oreMax) : [],
            'slot' => $tuttiGliSlot->keyBy(fn (Slot $s) => $s->giorno.'-'.$s->ordine),
            'lezioni' => $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id),
            'sostegni' => $compresenze->groupBy(fn ($c) => $c->slot_id.'-'.$c->classe_id),
            'discipline' => $lezioni->pluck('cattedra.disciplina')->unique('id')->sortBy('codice'),
            'limite' => $limite,
            'fontPx' => $fontPx,
            'legendaOre' => $legendaOre,
        ])->setPaper('a3', 'landscape');
    }
}
