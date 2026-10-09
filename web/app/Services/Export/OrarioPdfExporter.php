<?php

namespace App\Services\Export;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\Sede;
use App\Services\AssistenzaPause;
use App\Services\SedeCorrente;
use App\Services\Laboratori;
use App\Services\Editor\SpostamentiAula;
use App\Support\ColoriDiscipline;
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

        $fogli = $classi->map(function (Classe $classe) use ($orario, $sostegni) {
            $lezioni = Lezione::query()
                ->where('orario_id', $orario->id)
                ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
                ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'aula', 'slot')
                ->get();
            $cambi = app(SpostamentiAula::class)->cambi($lezioni);

            return [
                'titolo' => $this->conSede("Orario classe {$classe->nomeCompleto()}"),
                'slotPerGiorno' => Slot::perGiorno($classe->slotAttivi()->max('ordine')),
                'lezioni' => $lezioni->keyBy('slot_id'),
                // L'aula si scrive se non è quella della classe o se è cambiata rispetto all'ora prima (freccia →).
                'colonna' => function (Lezione $l) use ($cambi) {
                    $cambio = $cambi[$l->id] ?? null;
                    $aula = $l->aulaDaMostrare() ?? ($cambio['a'] ?? null);

                    return $l->cattedra->disciplina->nome."\n".$l->cattedra->docente->nomeCompleto().($l->con_clil && $l->cattedra->docenteClil ? "\n+ ".$l->cattedra->docenteClil->nomeCompleto().' (CLIL)' : '').($aula ? "\n".($cambio ? '→ ' : '').$aula->nome : '');
                },
                'sostegni' => ($sostegni[$classe->id] ?? collect())->groupBy('slot_id')
                    ->map(fn ($gruppo) => $gruppo->pluck('docente.cognome')->unique()->values()->all())->all(),
            ];
        })->all();

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
            ->with('cattedra.classe', 'cattedra.disciplina', 'aula')->get()
            ->flatMap(fn (Lezione $l) => array_map(fn ($id) => [$id, $l], $l->docentiIds()))->groupBy(0)->map(fn ($coppie) => $coppie->pluck(1));   // anche il docente CLIL in compresenza
        $compresenze = $orario->compresenzeSostegno()->with('classe')->get()->groupBy('docente_id');

        $docenti ??= Docente::query()->whereIn('id', $lezioni->keys()->merge($compresenze->keys())->unique())
            ->orderBy('cognome')->orderBy('nome')->get();

        $assistenza = app(AssistenzaPause::class);
        $fogli = $docenti->map(function (Docente $docente) use ($lezioni, $compresenze, $assistenza) {
            $sue = ($lezioni[$docente->id] ?? collect())->keyBy('slot_id');
            $sostegni = ($compresenze[$docente->id] ?? collect());

            return [
                'titolo' => $this->conSede("Orario docente {$docente->nomeCompleto()}"),
                'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $sue->keys()->merge($sostegni->pluck('slot_id')))->max('ordine')),
                'lezioni' => $sue,
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome.($l->con_clil && $l->cattedra->docente_clil_id === $docente->id ? ' (CLIL)' : '').($l->aulaDaMostrare() ? "\n".$l->aulaDaMostrare()->nome : ''),
                'sostegni' => $sostegni->groupBy('slot_id')->map(fn ($g) => $g->map(fn ($c) => $c->classe->nomeCompleto())->unique()->values()->all())->all(),
                'assistenze' => $assistenza->elenco($docente->loadMissing('assistenzePausa')),
                'laboratori' => app(Laboratori::class)->elenco($docente),
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
                'titolo' => $this->conSede("Orario aula {$aula->nome}"),
                'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $sue->keys())->max('ordine')),
                'lezioni' => $sue,
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome."\n".$l->cattedra->docente->nomeCompleto(),
                'sostegni' => [],
                'laboratori' => app(Laboratori::class)->elenco(null, $aula->id),
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['fogli' => $fogli])->setPaper('a4', 'landscape');
    }

    /** Con più sedi il nome della sede è nel titolo: i fogli stampati di sedi diverse non si confondono. */
    private function conSede(string $titolo): string
    {
        $sede = Sede::query()->count() > 1 ? Sede::query()->find(app(SedeCorrente::class)->id()) : null;

        return $sede ? "{$titolo} – {$sede->nome}" : $titolo;
    }

    /** Stessa regola di Lezione::scopeInAula, su lezioni già caricate. */
    private function inAula(Lezione $l, Aula $aula): bool
    {
        return $l->aula_id === $aula->id || ($l->aula_id === null && $l->cattedra->classe->aula_base_id === $aula->id);
    }

    /**
     * Tabellone su un solo foglio: una riga per classe (o per aula con $per = 'aula'), colonne raggruppate per giorno con
     * la stessa larghezza per tutte le ore, un colore per disciplina. Le ore vuote non compaiono; i docenti di sostegno
     * in compresenza sono visibili in cella (nella vista per classe).
     */
    public function generale(Orario $orario, string $per = 'classe'): PdfDocument
    {
        $classi = Classe::query()->orderBy('anno_corso')->orderBy('sezione')->get();
        $lezioni = Lezione::query()
            ->where('orario_id', $orario->id)
            ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'aula')
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
                'prima' => $ora->pausa_prima_minuti ? ['da' => $ora->inizioPausaPrima(), 'minuti' => $ora->pausa_prima_minuti, 'nome' => mb_strtolower($ora->nomePausaPrima()), 'aula' => $ora->pausaPrimaAula?->nome] : null,
                'ricreazione' => $ora->fineRicreazione() && $prossima
                    ? ['fine' => $ora->fineRicreazione(), 'minuti' => $ora->ricreazione_minuti, 'nome' => mb_strtolower($ora->nomePausa()), 'aula' => $ora->ricreazioneAula?->nome] : null,
            ] : null;
        })->filter()->values()->all();

        if ($per === 'aula') {
            $usate = $lezioni->map(fn (Lezione $l) => SpostamentiAula::aulaEffettiva($l)?->id)->filter()->unique();
            $righe = Aula::query()->orderBy('nome')->get()->filter(fn (Aula $a) => $usate->contains($a->id))
                ->map(fn (Aula $a) => ['id' => $a->id, 'etichetta' => $a->nome])->values();
            if ($lezioni->contains(fn (Lezione $l) => ! SpostamentiAula::aulaEffettiva($l))) {
                $righe->push(['id' => 0, 'etichetta' => 'Senza aula']);
            }
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.(SpostamentiAula::aulaEffettiva($l)?->id ?? 0));
        } else {
            $righe = $classi->map(fn (Classe $c) => ['id' => $c->id, 'etichetta' => $c->nomeCompleto()]);
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id);
        }

        return Pdf::loadView('orari.pdf.tabellone', [
            'titolo' => $this->conSede($per === 'aula' ? 'Quadro generale orario per aula' : 'Quadro generale orario'),
            'per' => $per,
            'righe' => $righe,
            'colori' => ColoriDiscipline::mappa(),
            'giorni' => $giorni,
            'ore' => $oreMax ? range(1, $oreMax) : [],
            'slot' => $tuttiGliSlot->keyBy(fn (Slot $s) => $s->giorno.'-'.$s->ordine),
            'celle' => $celle,
            'sostegni' => $compresenze->groupBy(fn ($c) => $c->slot_id.'-'.$c->classe_id),
            'discipline' => $lezioni->pluck('cattedra.disciplina')->unique('id')->sortBy('codice'),
            'limite' => $limite,
            'fontPx' => $fontPx,
            'legendaOre' => $legendaOre,
        ])->setPaper('a3', 'landscape');
    }
}
