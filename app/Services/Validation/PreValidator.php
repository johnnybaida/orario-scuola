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
    public function esegui(): array
    {
        return [
            ...$this->oreQuadroVsCattedre(),
            ...$this->oreDocenteVsSlotDisponibili(),
            ...$this->capacitaAuleTipo(),
            ...$this->vincoliContraddittori(),
        ];
    }

    private function oreQuadroVsCattedre(): array
    {
        $problemi = [];

        foreach (Classe::query()->with('quadroOrario', 'slotAttivi')->get() as $classe) {
            $oreQuadro = $classe->quadroOrario->ore_totali;
            $oreCattedre = Cattedra::query()->where('classe_id', $classe->id)->sum('ore');
            $nSlotAttivi = $classe->slotAttivi()->count();

            if ($oreCattedre != $oreQuadro) {
                $problemi[] = "Classe {$classe->nomeCompleto()}: il quadro orario prevede {$oreQuadro}h "
                    ."ma le cattedre assegnate coprono {$oreCattedre}h.";
            }

            if ($nSlotAttivi !== $oreQuadro) {
                $problemi[] = "Classe {$classe->nomeCompleto()}: {$nSlotAttivi} slot attivi ma il quadro "
                    ."orario richiede {$oreQuadro}h (devono coincidere).";
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
                $problemi[] = "Docente {$docente->nomeCompleto()}: {$oreAssegnate}h assegnate ma solo "
                    ."{$slotDisponibili} slot disponibili (indisponibilità escluse).";
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
                $problemi[] = "Nessuna aula di tipo '{$tipo}' censita, ma servono {$oreTotali}h settimanali.";
            } elseif ($oreTotali > $capacitaSettimanale) {
                $problemi[] = "Aule di tipo '{$tipo}': servono {$oreTotali}h settimanali ma la capacità "
                    ."massima teorica è {$capacitaSettimanale}h.";
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
                    $problemi[] = "Vincolo D1 su {$disciplina->nome} per {$classe?->nomeCompleto()}: richiede "
                        ."{$minRichiesto}h ma la cattedra ne assegna solo {$ore}.";
                }
            }
        }

        return $problemi;
    }
}
