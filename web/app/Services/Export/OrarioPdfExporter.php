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
                ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'aula', 'slot')
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

                    return $l->cattedra->disciplina->nome."\n".$this->nomeDocente($l).($l->docenteClilEffettivo() ? "\n+ ".$this->nomeClil($l).' (CLIL)' : '').($aula ? "\n".($cambio ? '→ ' : '').$aula->nomeConPiano() : '');
                },
                'sostegni' => ($sostegni[$classe->id] ?? collect())->groupBy('slot_id')
                    ->map(fn ($gruppo) => $gruppo->pluck('docente.cognome')->unique()->values()->all())->all(),
                'senzaOra' => $this->senzaOra($classe->cattedre()->with('disciplina', 'docente')->get(), false),
                'classeId' => $classe->id,
                'slotAttiviIds' => $classe->slotAttivi()->pluck('slot.id'),
                // I docenti della mensa (cattedre «senza ora»): compaiono nella riga della pausa nei giorni di rientro.
                'mensa' => $this->mensaDellaClasse($classe),
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['origine' => $this->origine($orario), 'fogli' => $fogli, 'sorveglianti' => $this->sorveglianti(), 'pauseMensa' => app(\App\Services\Mensa::class)->pause()->keys()->all()])->setPaper('a3', 'landscape');
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
            ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'aula')->get()
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
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome.($l->docenteClilEffettivo()?->id === $docente->id ? ' (CLIL)'.($l->clil_sostituto_id ? ' (per '.$l->cattedra->docenteClil?->cognome.')' : '') : '').($l->docenteEffettivo()->id === $docente->id && $l->docente_sostituto_id ? ' (per '.$l->cattedra->docente->cognome.')' : '').($l->docenteClilEffettivo() && $l->docenteClilEffettivo()->id !== $docente->id ? "\n+ ".$this->nomeClil($l).' (CLIL)' : '').($l->aulaDaMostrare() ? "\n".$l->aulaDaMostrare()->nomeConPiano() : ''),
                'sostegni' => $sostegni->groupBy('slot_id')->map(fn ($g) => $g->map(fn ($c) => $c->classe->nomeCompleto())->unique()->values()->all())->all(),
                'assistenze' => $assistenza->elenco($docente->loadMissing('assistenzePausa')),
                'senzaOra' => $this->senzaOra($docente->cattedre()->with('disciplina', 'classe')->get(), true),
                'docenteId' => $docente->id,
                'laboratori' => app(Laboratori::class)->elenco($docente),
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['origine' => $this->origine($orario), 'fogli' => $fogli, 'sorveglianti' => $this->sorveglianti(), 'pauseMensa' => app(\App\Services\Mensa::class)->pause()->keys()->all()])->setPaper('a3', 'landscape');
    }

    /**
     * Un foglio A4 orizzontale per aula (il foglio da appendere alla porta): per ogni ora la classe, la disciplina e il
     * docente. Comprende anche l'aula base delle classi. Senza argomento: tutte le aule usate in questo orario.
     */
    public function aule(Orario $orario, ?Collection $aule = null): PdfDocument
    {
        $lezioni = Lezione::query()->where('orario_id', $orario->id)
            ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'aula')->get();

        $aule ??= Aula::query()->orderBy('nome')->get()->filter(fn (Aula $a) => $lezioni->contains(fn (Lezione $l) => $this->inAula($l, $a)))->values();

        $fogli = $aule->map(function (Aula $aula) use ($lezioni) {
            $sue = $lezioni->filter(fn (Lezione $l) => $this->inAula($l, $aula))->groupBy('slot_id');

            return [
                'titolo' => $this->conSede("Orario aula {$aula->nomeConPiano()}"),
                'slotPerGiorno' => Slot::perGiorno(Slot::query()->whereIn('id', $sue->keys())->max('ordine')),
                'lezioni' => $sue,
                'colonna' => fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' - '.$l->cattedra->disciplina->nome."\n".$this->nomeDocente($l).($l->docenteClilEffettivo() ? "\n+ ".$this->nomeClil($l).' (CLIL)' : ''),
                'sostegni' => [],
                'senzaDocentiInPausa' => true,   // il foglio è per l'aula: la pausa (anche la mensa) è una riga come le altre, senza i docenti
                'laboratori' => app(Laboratori::class)->elenco(null, $aula->id),
            ];
        })->all();

        return Pdf::loadView('orari.pdf.griglia', ['origine' => $this->origine($orario), 'fogli' => $fogli, 'sorveglianti' => $this->sorveglianti(), 'pauseMensa' => app(\App\Services\Mensa::class)->pause()->keys()->all()])->setPaper('a3', 'landscape');
    }

    /** Con più sedi il nome della sede è nel titolo: i fogli stampati di sedi diverse non si confondono. */
    /** Da dove viene l'orario (id e seed della generazione): piccola nota a piè di pagina per risalire a come è stato generato. */
    private function origine(Orario $orario): string
    {
        return "Orario #{$orario->id} · seed {$orario->seed}";
    }

    /** Il docente della lezione; se è un sostituto, «Verdi (per Rossi)». */
    private function nomeDocente(Lezione $l): string
    {
        return $l->docenteEffettivo()->nomeCompleto().($l->docente_sostituto_id ? ' (per '.$l->cattedra->docente->cognome.')' : '');
    }

    private function nomeClil(Lezione $l): string
    {
        return $l->docenteClilEffettivo()->nomeCompleto().($l->clil_sostituto_id && $l->cattedra->docenteClil ? ' (per '.$l->cattedra->docenteClil->cognome.')' : '');
    }

    private function conSede(string $titolo): string
    {
        $sede = Sede::query()->count() > 1 ? Sede::query()->find(app(SedeCorrente::class)->id()) : null;

        return $sede ? "{$titolo} – {$sede->nome}" : $titolo;
    }

    /**
     * Chi sorveglia ogni pausa, giorno per giorno (assistenza alle pause dell'anagrafica): [ordine dell'ora che precede => [giorno => [nomi]]].
     * Le assistenze su pause che non esistono più non compaiono.
     */
    private function sorveglianti(): array
    {
        $pause = app(AssistenzaPause::class)->pause();

        return \App\Models\AssistenzaPausa::query()->with('docente', 'classi')->get()->filter(fn ($a) => $pause->has($a->ordine))
            ->sortBy(fn ($a) => $a->docente->cognome.$a->docente->nome)
            ->groupBy('ordine')->map(fn ($per) => $per->groupBy('giorno')->sortKeys()->map(fn ($g) => $g->map(fn ($a) => [
                'docente_id' => $a->docente_id, 'nome' => $a->docente->nomeCompleto(),
                'classi' => $a->classi->pluck('id')->all(),          // vuoto = tutte le classi in mensa quel giorno
                'classiNomi' => $a->classi->map->nomeCompleto()->all(),
            ])->values()->all())->all())->all();
    }

    /**
     * Le discipline «senza ora» (mensa) di una classe con i loro docenti: [['pausa' => ordine dell'ora che precede la pausa o null,
     * 'disciplina' => nome, 'docenti' => [nomi]]].
     *
     * @return list<array{pausa: ?int, disciplina: string, docenti: list<string>}>
     */
    private function mensaDellaClasse(Classe $classe): array
    {
        return $classe->cattedre()->with('disciplina', 'docente')->get()->filter(fn ($c) => $c->disciplina->senza_slot)
            ->groupBy('disciplina_id')->map(fn ($g) => [
                'pausa' => $g->first()->disciplina->pausa_dopo_ora, 'disciplina' => $g->first()->disciplina->nome,
                'docenti' => $g->map(fn ($c) => $c->docente->nomeCompleto())->unique()->values()->all(),
            ])->values()->all();
    }

    /** «Pranzo: Rossi Anna (2h)»: le cattedre di discipline «senza ora» (mensa), che non sono lezioni e non stanno nella griglia. */
    private function senzaOra(iterable $cattedre, bool $conClasse): array
    {
        return collect($cattedre)->filter(fn ($c) => $c->disciplina->senza_slot)->map(fn ($c) => $conClasse
            ? "{$c->disciplina->nome} in {$c->classe->nomeCompleto()} ({$c->ore}h)"
            : "{$c->disciplina->nome}: {$c->docente->nomeCompleto()} ({$c->ore}h)".($c->compresenza ? ', in compresenza' : ''))->values()->all();
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
            ->with('cattedra.classe.aulaBase', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'aula')
            ->get();
        $compresenze = $orario->compresenzeSostegno()->with('docente')->get();

        // Giorni e ore realmente usati; ogni giorno ha tante colonne quante l'ora più alta usata in qualunque giorno.
        $usati = Slot::query()->whereIn('id', $lezioni->pluck('slot_id')->merge($compresenze->pluck('slot_id')))->get();
        $giorni = $usati->pluck('giorno')->unique()->sort()->values();
        $oreMax = (int) $usati->max('ordine');

        // Carattere il più grande possibile (8-14px) perché ~9 caratteri stiano in una colonna dell'A2 orizzontale;
        // materie e cognomi più lunghi vengono comunque troncati con "…".
        // Colonne extra per le pause in cui si svolge una disciplina «senza ora» (mensa), solo nella vista per classe.
        $pause = app(AssistenzaPause::class)->pause();
        $mensa = \App\Models\Disciplina::query()->where('senza_slot', true)->whereNotNull('pausa_dopo_ora')->get()->filter(fn ($d) => $pause->has($d->pausa_dopo_ora));
        $pauseMensa = app(\App\Services\Mensa::class)->pause();
        $colonnePausa = $per === 'classe' ? $mensa->pluck('pausa_dopo_ora')->merge($pauseMensa->keys())->unique()->sort()->values()->all() : [];

        // Legenda: orario di ogni ora e ricreazioni (uguali per tutti i giorni: si leggono dal primo slot di ciascuna ora).
        $tuttiGliSlot = Slot::query()->orderBy('giorno')->orderBy('ordine')->get();
        $legendaOre = collect(range(1, max(1, $oreMax)))->map(function (int $ordine) use ($tuttiGliSlot) {
            $ora = $tuttiGliSlot->first(fn (Slot $s) => $s->ordine === $ordine);
            $prossima = $tuttiGliSlot->first(fn (Slot $s) => $s->ordine === $ordine + 1);

            return $ora ? [
                'ordine' => $ordine, 'inizio' => substr($ora->inizio, 0, 5), 'fine' => substr($ora->fine, 0, 5),
                'prima' => $ora->pausa_prima_minuti ? ['da' => $ora->inizioPausaPrima(), 'minuti' => $ora->pausa_prima_minuti, 'nome' => mb_strtolower($ora->nomePausaPrima()), 'aula' => $ora->pausaPrimaAula?->nomeConPiano()] : null,
                'ricreazione' => $ora->fineRicreazione() && $prossima
                    ? ['fine' => $ora->fineRicreazione(), 'minuti' => $ora->ricreazione_minuti, 'nome' => mb_strtolower($ora->nomePausa()), 'aula' => $ora->ricreazioneAula?->nomeConPiano()] : null,
            ] : null;
        })->filter()->values()->all();

        // Cambi d'aula (nella vista per classe si scrive l'aula con la freccia dove la classe si sposta).
        $cambi = [];
        if ($per === 'classe') {
            foreach ($lezioni->groupBy(fn (Lezione $l) => $l->cattedra->classe_id) as $dellaClasse) {
                $cambi += app(SpostamentiAula::class)->cambi($dellaClasse->load('slot'));
            }
        }

        if ($per === 'aula') {
            $usate = $lezioni->map(fn (Lezione $l) => SpostamentiAula::aulaEffettiva($l)?->id)->filter()->unique();
            $righe = Aula::query()->orderBy('nome')->get()->filter(fn (Aula $a) => $usate->contains($a->id))
                ->map(fn (Aula $a) => ['id' => $a->id, 'etichetta' => $a->nomeConPiano(true)])->values();   // piano abbreviato: la colonna è stretta
            if ($lezioni->contains(fn (Lezione $l) => ! SpostamentiAula::aulaEffettiva($l))) {
                $righe->push(['id' => 0, 'etichetta' => 'Senza aula']);
            }
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.(SpostamentiAula::aulaEffettiva($l)?->id ?? 0));
        } else {
            $righe = $classi->map(fn (Classe $c) => ['id' => $c->id, 'etichetta' => $c->nomeCompleto()]);
            $celle = $lezioni->groupBy(fn (Lezione $l) => $l->slot_id.'-'.$l->cattedra->classe_id);
        }

        // Sorveglianti della mensa per classe, giorno e pausa (assistenze alle pause): [classe-giorno-pausa => [cognomi]].
        $celleSorveglianza = [];
        if ($per === 'classe' && $pauseMensa->isNotEmpty()) {
            $classiConSlot = Classe::query()->with('slotAttivi')->get();
            $cognomi = \App\Models\Docente::query()->pluck('cognome', 'id');
            foreach ($pauseMensa as $ordine => $pausa) {
                foreach (app(\App\Services\Mensa::class)->assegnazioni($ordine, $classiConSlot) as $giorno => $perClasse) {
                    foreach ($perClasse as $classeId => $docenti) {
                        $celleSorveglianza[$classeId.'-'.$giorno.'-'.$ordine] = collect($docenti)->map(fn ($d) => $cognomi[$d] ?? '?')->unique()->values()->all();
                    }
                }
            }
        }

        // Cella della pausa: [classe-giorno-pausa => [['disciplina' => Disciplina, 'docenti' => [cognomi]]]], solo nei giorni di rientro
        // (la classe ha ore dopo la pausa).
        $celleMensa = [];
        if ($colonnePausa) {
            foreach (Classe::query()->with('slotAttivi', 'cattedre.disciplina', 'cattedre.docente')->get() as $classe) {
                foreach ($classe->cattedre->filter(fn ($c) => $mensa->contains('id', $c->disciplina_id))->groupBy('disciplina_id') as $gruppo) {
                    $disciplina = $gruppo->first()->disciplina;
                    foreach ($classe->slotAttivi->groupBy('giorno') as $giorno => $slotDelGiorno) {
                        if ($slotDelGiorno->contains(fn (Slot $s) => $s->ordine > $disciplina->pausa_dopo_ora)) {
                            $celleMensa[$classe->id.'-'.$giorno.'-'.$disciplina->pausa_dopo_ora][] = ['disciplina' => $disciplina, 'docenti' => $gruppo->pluck('docente.cognome')->unique()->values()->all()];
                        }
                    }
                }
            }
        }

        // Colonne di ogni giorno: solo le ore con almeno una lezione (o un sostegno) quel giorno e le pause con qualcuno da mostrare (mensa,
        // sorveglianza): le colonne vuote non si stampano e lo spazio va alle altre, che crescono insieme al carattere.
        $oreUsate = $usati->groupBy('giorno')->map(fn ($slot) => $slot->pluck('ordine')->unique()->all());
        $pausePiene = collect(array_merge(array_keys($celleMensa), array_keys($celleSorveglianza)))
            ->mapWithKeys(function (string $chiave) {
                [, $giorno, $ora] = explode('-', $chiave);

                return [(int) $giorno.'-'.(int) $ora => true];
            });
        $colonnePerGiorno = $giorni->mapWithKeys(function ($giorno) use ($oreMax, $oreUsate, $colonnePausa, $pausePiene) {
            $colonne = in_array(0, $colonnePausa) && $pausePiene->has("{$giorno}-0") ? [['pausa', 0]] : [];
            foreach (range(1, max(1, $oreMax)) as $ora) {
                if (in_array($ora, $oreUsate[$giorno] ?? [], true)) {
                    $colonne[] = ['ora', $ora];
                }
                if (in_array($ora, $colonnePausa) && $pausePiene->has("{$giorno}-{$ora}")) {
                    $colonne[] = ['pausa', $ora];
                }
            }

            return [$giorno => $colonne];
        });
        $larghezzaColonna = 3050 / max(1, $colonnePerGiorno->sum(fn ($c) => count($c)));
        $fontPx = max(10, min(22, (int) floor(($larghezzaColonna - 3) / 3.6)));
        $limite = max(4, (int) floor(($larghezzaColonna - 3) / (0.5 * $fontPx)));

        // Celle più alte per leggere meglio: lo spazio verticale dell'A1 (circa 2000px) si divide tra le righe (quasi tutte con 4-5 righe di testo per cella).
        // Righe di testo della cella più piena (materia, docente, aula, CLIL, un rigo per docente di sostegno): l'altezza delle righe della tabella
        // la decide lei, quindi si riduce il carattere finché tutte le righe stanno nel foglio.
        $righeDiTesto = $celle->map(function ($gruppo, $chiave) use ($per, $cambi, $compresenze) {
            if ($per === 'aula') {
                return $gruppo->sum(fn (Lezione $l) => 3 + ($l->docenteClilEffettivo() ? 1 : 0) + $compresenze->where('slot_id', $l->slot_id)->where('classe_id', $l->cattedra->classe_id)->unique('docente_id')->count());
            }

            return $gruppo->sum(fn (Lezione $l) => 2 + ((SpostamentiAula::aulaEffettiva($l) || isset($cambi[$l->id])) ? 1 : 0) + ($l->docenteClilEffettivo() ? 1 : 0))
                + $compresenze->where('slot_id', $gruppo->first()->slot_id)->where('classe_id', $gruppo->first()->cattedra->classe_id)->unique('docente_id')->count();
        })->max() ?: 3;
        $altezzaRiga = 1700 / max(1, $righe->count());
        while ($fontPx > 8 && $righeDiTesto * $fontPx * 1.25 + 6 > $altezzaRiga) {
            $fontPx--;
        }
        $limite = max(4, (int) floor(($larghezzaColonna - 3) / (0.5 * $fontPx)));

        // Ogni riga di una cella occupa tutta la larghezza e, se il testo non entra, si accorcia con «…»: la misura è quella reale del carattere del PDF.
        $metriche = app('dompdf.wrapper')->getDomPDF()->getFontMetrics();
        $adatta = function (string $testo, string $prefisso = '', string $suffisso = '', bool $grassetto = false, ?int $px = null) use ($metriche, $larghezzaColonna, $fontPx) {
            $carattere = $metriche->getFont('sans-serif', $grassetto ? 'bold' : 'normal');
            $massimo = ($larghezzaColonna - 8) * 0.75;   // pt: la colonna meno bordi e margini interni
            $misura = fn (string $t) => $metriche->getTextWidth($prefisso.$t.$suffisso, $carattere, ($px ?? $fontPx) * 0.75);
            if ($misura($testo) <= $massimo) {
                return $prefisso.$testo.$suffisso;
            }
            while (mb_strlen($testo) > 1 && $misura($testo.'…') > $massimo) {
                $testo = mb_substr($testo, 0, -1);
            }

            return $prefisso.$testo.'…'.$suffisso;
        };
        $paddingPx = (int) max(1, min(20, floor(($altezzaRiga - $righeDiTesto * $fontPx * 1.25) / 2)));

        return Pdf::loadView('orari.pdf.tabellone', [
            'origine' => $this->origine($orario),
            'cambi' => $cambi,
            'adatta' => $adatta,
            'altezzaCella' => (int) floor(1500 / max(1, $righe->count())),
            'paddingPx' => $paddingPx,
            'titolo' => $this->conSede($per === 'aula' ? 'Quadro generale orario per aula' : 'Quadro generale orario'),
            'per' => $per,
            'righe' => $righe,
            'colori' => ColoriDiscipline::mappa(),
            'giorni' => $giorni,
            'ore' => $oreMax ? range(1, $oreMax) : [],
            'colonnePerGiorno' => $colonnePerGiorno->all(),
            'slot' => $tuttiGliSlot->keyBy(fn (Slot $s) => $s->giorno.'-'.$s->ordine),
            'celle' => $celle,
            'sostegni' => $compresenze->groupBy(fn ($c) => $c->slot_id.'-'.$c->classe_id),
            'discipline' => $lezioni->pluck('cattedra.disciplina')->merge($colonnePausa ? $mensa : [])->unique('id')->sortBy('codice'),
            'colonnePausa' => $colonnePausa,
            'celleMensa' => $celleMensa,
            'celleSorveglianza' => $celleSorveglianza,
            'nomiPausa' => $pause->map(fn ($p) => $p['nome'])->all(),
            'limite' => $limite,
            'fontPx' => $fontPx,
            'legendaOre' => $legendaOre,
        ])->setPaper('a1', 'landscape');
    }
}
