<?php

namespace App\Services\Validation;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Slot;
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
            ...$this->oreDocenteVsSlotDisponibili(),
            ...$this->capacitaAuleTipo(),
            ...$this->vincoliContraddittori(),
            ...$this->oreSostegno(),
        ];
    }

    private function p(string $testo, string $url): array
    {
        return ['testo' => $testo, 'url' => $url];
    }

    private function oreQuadroVsCattedre(): array
    {
        $problemi = [];

        foreach (Classe::query()->with('quadroOrario', 'slotAttivi')->get() as $classe) {
            $oreQuadro = $classe->quadroOrario->ore_totali;
            $oreCattedre = Cattedra::query()->where('classe_id', $classe->id)->sum('ore');
            $nSlotAttivi = $classe->slotAttivi()->count();

            if ($oreCattedre != $oreQuadro) {
                $problemi[] = $this->p("Classe {$classe->nomeCompleto()}: il quadro orario prevede {$oreQuadro}h "
                    ."ma le cattedre assegnate coprono {$oreCattedre}h.", route('classi.edit', $classe));
            }

            if ($nSlotAttivi !== $oreQuadro) {
                $problemi[] = $this->p("Classe {$classe->nomeCompleto()}: {$nSlotAttivi} slot attivi ma il quadro "
                    ."orario richiede {$oreQuadro}h (devono coincidere).", route('classi.edit', $classe));
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
                $problemi[] = $this->p("Docente {$docente->nomeCompleto()}: {$sospensione->etichettaMotivo()} {$sospensione->periodo()}, "
                    ."ma ha {$docente->cattedre_count} cattedre: riassegnale a un supplente o chiudi la sospensione.", route('docenti.edit', $docente));
            }
        }

        return $problemi;
    }

    private function oreDocenteVsSlotDisponibili(): array
    {
        $problemi = [];
        $totaleSlot = Slot::query()->count();

        foreach (Docente::query()->withCount('indisponibilita')->withSum('cattedre', 'ore')->get() as $docente) {
            $slotDisponibili = $totaleSlot - $docente->indisponibilita_count;
            $oreAssegnate = $docente->cattedre_sum_ore ?? 0;

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

        $domandaPerTipo = Cattedra::query()
            ->join('discipline', 'discipline.id', '=', 'cattedre.disciplina_id')
            ->whereNotNull('discipline.tipo_aula_richiesto')
            ->selectRaw('discipline.tipo_aula_richiesto as tipo, sum(cattedre.ore) as ore_totali')
            ->groupBy('discipline.tipo_aula_richiesto')
            ->pluck('ore_totali', 'tipo');

        foreach ($domandaPerTipo as $tipo => $oreTotali) {
            $capacitaSettimanale = Aula::query()->where('tipo', $tipo)->sum('capienza') * $totaleSlot;

            if ($capacitaSettimanale === 0) {
                $problemi[] = $this->p("Nessuna aula di tipo '{$tipo}' censita, ma servono {$oreTotali}h settimanali.", route('aule.index'));
            } elseif ($oreTotali > $capacitaSettimanale) {
                $problemi[] = $this->p("Aule di tipo '{$tipo}': servono {$oreTotali}h settimanali ma la capacità "
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
