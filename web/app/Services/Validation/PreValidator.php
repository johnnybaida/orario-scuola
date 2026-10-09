<?php

namespace App\Services\Validation;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Slot;
use App\Models\Sospensione;
use App\Models\Vincolo;

/**
 * Controlli di capacità/coerenza prima del solving (§7.7 dell'analisi).
 * Ritorna un elenco di messaggi: vuoto = nessun problema rilevato.
 */
class PreValidator
{
    /** @return list<string> solo i messaggi (usato dalla generazione) */
    public function esegui(): array
    {
        return array_column($this->problemi(), 'testo');
    }

    /** @return list<array{testo: string, url: string}> messaggi con il link alla pagina dove correggerli */
    public function problemi(): array
    {
        return [
            ...$this->oreQuadroVsCattedre(),
            ...$this->docentiSospesi(),
            ...$this->sostituzioniDaChiudere(),
            ...$this->oreDocenteVsSlotDisponibili(),
            ...$this->capacitaAuleTipo(),
            ...$this->vincoliContraddittori(),
            ...$this->oreSostegno(),
        ];
    }

    /**
     * Cose da controllare che **non** bloccano la generazione (a differenza di `problemi()`): il solver non le usa, ma l'orario
     * stampato sarebbe incompleto. Ogni voce ha il link alla pagina dove correggerle.
     *
     * @return list<array{testo: string, url: string}>
     */
    public function avvisi(): array
    {
        return [
            ...$this->assistenzeSenzaPausa(),
            ...app(\App\Services\Mensa::class)->avvisi(),
        ];
    }

    /** Assistenza a una pausa che non c'è più in Scansione oraria: non compare nei PDF e non conta nel monte ore, quindi va segnalata. */
    private function assistenzeSenzaPausa(): array
    {
        $pause = app(\App\Services\AssistenzaPause::class)->pause();
        $avvisi = [];

        foreach (\App\Models\AssistenzaPausa::query()->with('docente')->get()->filter(fn ($a) => ! $pause->has($a->ordine)) as $a) {
            $giorno = mb_strtolower(Slot::GIORNI[$a->giorno] ?? (string) $a->giorno);
            $quando = $a->ordine === 0 ? 'prima della prima ora' : "dopo la {$a->ordine}ª ora";
            $avvisi[] = $this->p("Docente {$a->docente->nomeCompleto()}: l'assistenza del {$giorno} alla pausa {$quando} non è più valida, perché in Scansione oraria non c'è una pausa lì: non compare nei PDF e non conta nelle sue ore. Riassegnala a una pausa esistente o eliminala.", route('docenti.edit', $a->docente));
        }

        return $avvisi;
    }

    private function p(string $testo, string $url): array
    {
        return ['testo' => $testo, 'url' => $url];
    }

    private function oreQuadroVsCattedre(): array
    {
        $problemi = [];

        foreach (Classe::query()->with('quadroOrario', 'slotAttivi')->get() as $classe) {
            $oreMensa = (int) $classe->quadroOrario->ore_mensa;                  // ore di mensa dichiarate dal quadro (pausa pranzo)
            $oreQuadro = $classe->quadroOrario->ore_totali - $oreMensa;           // ore di discipline: le cattedre le devono coprire
            // Le discipline «senza ora» (mensa) contano nel quadro ma non sono lezioni: la seconda cattedra in compresenza non si somma.
            $cattedre = Cattedra::query()->where('classe_id', $classe->id)->with('disciplina')->get()
                ->reject(fn (Cattedra $c) => $c->compresenza && $c->disciplina->senza_slot);
            $oreCattedre = $cattedre->sum('ore');
            $oreSenzaOra = $cattedre->filter(fn (Cattedra $c) => $c->disciplina->senza_slot)->sum('ore');
            $nSlotAttivi = $classe->slotAttivi()->count();

            if ($oreCattedre != $oreQuadro) {
                // Se la classe va in mensa e il quadro non dichiara ore di mensa, la differenza è probabilmente quella.
                $suggerimento = $oreMensa === 0 && $oreCattedre < $oreQuadro && app(\App\Services\Mensa::class)->pause()->keys()->contains(fn ($o) => app(\App\Services\Mensa::class)->giorni($classe, $o))
                    ? ' Se la differenza sono le ore di mensa, scrivile in «Ore di mensa» nel quadro orario (e togli dal quadro l\'eventuale riga della mensa).' : '';
                $problemi[] = $this->p("Classe {$classe->nomeCompleto()}: il quadro orario prevede {$oreQuadro}h "
                    ."ma le cattedre assegnate coprono {$oreCattedre}h.{$suggerimento}", route($suggerimento ? 'quadri-orari.edit' : 'classi.edit', $suggerimento ? $classe->quadroOrario : $classe));
            }

            if ($nSlotAttivi !== $oreQuadro - $oreSenzaOra) {
                $attesi = $oreQuadro - $oreSenzaOra;
                $totale = $classe->quadroOrario->ore_totali;
                $problemi[] = $this->p("Classe {$classe->nomeCompleto()}: ha {$nSlotAttivi} slot attivi ma ne servono {$attesi}: il quadro è di {$totale}h"
                    .($oreSenzaOra + $oreMensa ? ' e '.($oreSenzaOra + $oreMensa)."h sono di mensa (non occupano un'ora di lezione)" : '')
                    .'. Aggiungi o togli ore negli «Slot attivi» della classe.', route('classi.edit', $classe));
            }
        }

        return $problemi;
    }

    /** Un docente con una sospensione in corso "esclusa dall'orario" non può avere cattedre: vanno riassegnate a un supplente. */
    private function docentiSospesi(): array
    {
        $problemi = [];

        foreach (Docente::query()->whereHas('cattedre')->withCount('cattedre')->with('sospensioni')->get() as $docente) {
            $sospensione = $docente->sospensioni->first(fn ($s) => $s->esclude_da_orario && $s->attivaIl(now()));
            if ($sospensione) {
                $problemi[] = $sospensione->supplenti()->exists()
                    ? $this->p("Docente {$docente->nomeCompleto()}: {$sospensione->etichettaMotivo()} {$sospensione->periodo()}, "
                        ."ma ha {$docente->cattedre_count} cattedre: passale ai supplenti indicati (Gestisci sostituzione) o chiudi la sospensione.", route('sostituzioni.form', $sospensione))
                    : $this->p("Docente {$docente->nomeCompleto()}: {$sospensione->etichettaMotivo()} {$sospensione->periodo()}, "
                        ."ma ha {$docente->cattedre_count} cattedre: riassegnale a un supplente o chiudi la sospensione.", route('docenti.edit', $docente));
            }
        }

        return $problemi;
    }

    /** Sospensione finita ma con cattedre ancora ai supplenti: il titolare è rientrato, vanno riportate a lui. */
    private function sostituzioniDaChiudere(): array
    {
        $problemi = [];

        foreach (Sospensione::query()->with('docente')->withCount('cattedreSostituite')->whereHas('cattedreSostituite')->get() as $sospensione) {
            if ($sospensione->al && $sospensione->al->endOfDay()->lt(now())) {
                $problemi[] = $this->p("Docente {$sospensione->docente->nomeCompleto()}: la sospensione è terminata il {$sospensione->al->format('d/m/Y')}, "
                    ."ma {$sospensione->cattedre_sostituite_count} cattedre sono ancora ai supplenti: riportale al titolare.", route('sostituzioni.form', $sospensione));
            }
        }

        return $problemi;
    }

    private function oreDocenteVsSlotDisponibili(): array
    {
        $problemi = [];
        $totaleSlot = Slot::query()->count();

        foreach (Docente::query()->withCount('indisponibilita')->withSum('cattedreClil', 'ore_clil')->with('cattedre.disciplina')->get() as $docente) {
            $slotDisponibili = $totaleSlot - $docente->indisponibilita_count;
            $oreAssegnate = $docente->cattedre->reject(fn ($c) => $c->disciplina->senza_slot)->sum('ore') + (int) $docente->cattedre_clil_sum_ore_clil;   // la mensa non occupa slot; le ore CLIL sì

            if ($oreAssegnate > $slotDisponibili) {
                $problemi[] = $this->p("Docente {$docente->nomeCompleto()}: {$oreAssegnate}h assegnate ma solo "
                    ."{$slotDisponibili} slot disponibili (indisponibilità escluse).", route('docenti.edit', $docente));
            }
        }

        return $problemi;
    }

    private function capacitaAuleTipo(): array
    {
        $problemi = [];
        $totaleSlot = Slot::query()->count();

        // Domanda per insieme di tipi ammessi (una disciplina può avere un'aula propria e una condivisa).
        $domanda = Cattedra::query()->with('disciplina')->get()->filter(fn ($c) => $c->disciplina->tipiAmmessi())
            ->groupBy(fn ($c) => implode('|', $c->disciplina->tipiAmmessi()))->map(fn ($g) => $g->sum('ore'));

        foreach ($domanda as $chiave => $oreTotali) {
            $tipi = explode('|', $chiave);
            $etichetta = implode("' o '", $tipi);
            $capacitaSettimanale = Aula::query()->whereIn('tipo', $tipi)->sum('capienza') * $totaleSlot;

            if ($capacitaSettimanale === 0) {
                $problemi[] = $this->p("Nessuna aula di tipo '{$etichetta}' censita, ma servono {$oreTotali}h settimanali.", route('aule.index'));
            } elseif ($oreTotali > $capacitaSettimanale) {
                $problemi[] = $this->p("Aule di tipo '{$etichetta}': servono {$oreTotali}h settimanali ma la capacità "
                    ."massima teorica è {$capacitaSettimanale}h.", route('aule.index'));
            }
        }

        return $problemi;
    }

    private function oreSostegno(): array
    {
        $problemi = [];

        foreach (Classe::query()->whereHas('fabbisogniSostegno')->with('fabbisogniSostegno', 'assegnazioniSostegno')->get() as $classe) {
            $oreAssegnate = $classe->assegnazioniSostegno->sum('ore');
            $oreRichieste = $classe->conteggioSostegnoEffettivo() === 'per_classe'
                ? $classe->fabbisogniSostegno->max('ore_settimanali')
                : $classe->fabbisogniSostegno->sum('ore_settimanali');

            if ($oreAssegnate < $oreRichieste) {
                $problemi[] = $this->p("Sostegno classe {$classe->nomeCompleto()}: servono {$oreRichieste}h "
                    ."({$classe->conteggioSostegnoEffettivo()}) ma i docenti assegnati coprono solo {$oreAssegnate}h.", route('classi.edit', $classe));
            }

            foreach ($classe->fabbisogniSostegno as $fabbisogno) {
                if ($fabbisogno->docente_unico && $classe->assegnazioniSostegno->isEmpty()) {
                    $problemi[] = $this->p("Sostegno classe {$classe->nomeCompleto()}: il fabbisogno {$fabbisogno->codice_anonimo} "
                        .'richiede un docente unico ma nessun docente di sostegno è assegnato.', route('classi.edit', $classe));
                }
            }
        }

        return $problemi;
    }

    private function vincoliContraddittori(): array
    {
        $problemi = [];

        foreach (Vincolo::attivi()->where('tipo', 'D1_BLOCCO_MIN_CONSECUTIVO')->get() as $vincolo) {
            $disciplina = Disciplina::query()->find($vincolo->parametri['disciplina_id'] ?? null);
            if (! $disciplina) {
                continue;
            }

            $classeIds = $vincolo->ambito_livello === 'classe'
                ? $vincolo->ambito_ids
                : Classe::query()->pluck('id')->all();

            foreach ($classeIds as $classeId) {
                $ore = Cattedra::query()->where('classe_id', $classeId)->where('disciplina_id', $disciplina->id)->sum('ore');
                $minRichiesto = $vincolo->parametri['min_consecutive'] * ($vincolo->parametri['n_blocchi_min'] ?? 1);

                if ($ore > 0 && $ore < $minRichiesto) {
                    $classe = Classe::find($classeId);
                    $problemi[] = $this->p("Vincolo D1 su {$disciplina->nome} per {$classe?->nomeCompleto()}: richiede "
                        ."{$minRichiesto}h ma la cattedra ne assegna solo {$ore}.", route('vincoli.index'));
                }
            }
        }

        return $problemi;
    }
}
