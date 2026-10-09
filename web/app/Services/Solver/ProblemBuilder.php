<?php

namespace App\Services\Solver;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Slot;
use App\Models\Vincolo;

/**
 * Traduce l'anagrafica corrente (cattedre, vincoli, scansione oraria) nel
 * JSON del contratto PHP <-> solver descritto in CLAUDE.md.
 */
class ProblemBuilder
{
    /** @var array<int, int> lezione_id (input solver) => cattedra_id, popolata da costruisci(). */
    private array $mappaLezioni = [];

    /** @var array<int, true> id lezione del solver => la lezione è in compresenza CLIL */
    private array $lezioniClil = [];

    public function lezioniClil(): array
    {
        return $this->lezioniClil;
    }

    public function mappaLezioni(): array
    {
        return $this->mappaLezioni;
    }

    public function costruisci(int $seed, int $timeLimitS): array
    {
        return [
            'seed' => $seed,
            'time_limit_s' => $timeLimitS,
            'slots' => $this->slots(),
            'aule' => $this->aule(),
            'docenti' => $this->docenti(),
            'classi' => $this->classi(),
            'occupazioni_fisse' => app(\App\Services\Laboratori::class)->perSolver(),
            'lezioni' => $this->lezioni(),
            'sostegno' => $this->sostegno(),
            'vincoli' => $this->vincoli(),
        ];
    }

    private function slots(): array
    {
        return Slot::query()->get()->map(fn (Slot $s) => [
            'id' => $s->id,
            'giorno' => $s->giorno,
            'ordine' => $s->ordine,
            'intervallo_dopo' => $s->intervallo_dopo,
        ])->all();
    }

    private function aule(): array
    {
        return Aula::query()->get()->map(fn (Aula $a) => [
            'id' => $a->id,
            'tipo' => $a->tipo,
            'capacita' => $a->capienza,
            'piano' => $a->piano,
        ])->all();
    }

    private function docenti(): array
    {
        return Docente::query()->with('indisponibilita')->get()->map(fn (Docente $d) => [
            'id' => $d->id,
            'indisponibili' => $d->indisponibilita->pluck('id')->all(),
        ])->all();
    }

    private function classi(): array
    {
        return Classe::query()->with('slotAttivi')->get()->map(fn (Classe $c) => [
            'id' => $c->id,
            'slots_attivi' => $c->slotAttivi->pluck('id')->all(),
            'piano' => $c->piano,
        ])->all();
    }

    private function lezioni(): array
    {
        $lezioni = [];
        $prossimoId = 1;

        $cattedre = Cattedra::query()->with('disciplina')->get()->reject(fn (Cattedra $c) => $c->disciplina->senza_slot);   // la mensa non è una lezione

        foreach ($cattedre as $cattedra) {
            for ($i = 0; $i < $cattedra->ore; $i++) {
                $id = $prossimoId++;
                // Le prime `ore_clil` lezioni della cattedra hanno anche il docente CLIL (le lezioni di una cattedra sono intercambiabili: il solver sceglie quando).
                $conClil = $cattedra->docente_clil_id && $i < $cattedra->ore_clil;
                $conClil && $this->lezioniClil[$id] = true;
                $lezioni[] = [
                    'id' => $id,
                    'classi' => [$cattedra->classe_id],
                    'docenti' => $conClil ? [$cattedra->docente_id, $cattedra->docente_clil_id] : [$cattedra->docente_id],
                    'disciplina' => $cattedra->disciplina->codice,
                    'durata' => 1,
                    'tipo_aula' => $cattedra->disciplina->tipo_aula_richiesto,
                    'tipi_aula' => $cattedra->disciplina->tipiAmmessi(),   // più tipi se la disciplina ha aule proprie e condivise
                    'gruppo_parallelo' => null,
                    'bloccata_slot' => null,
                ];
                $this->mappaLezioni[$id] = $cattedra->id;
            }
        }

        return $lezioni;
    }

    private function sostegno(): array
    {
        return Classe::query()
            ->whereHas('fabbisogniSostegno')
            ->with('fabbisogniSostegno', 'assegnazioniSostegno')
            ->get()
            ->map(fn (Classe $c) => [
                'classe' => $c->id,
                'fabbisogni' => $c->fabbisogniSostegno->map(fn ($f) => [
                    'codice' => $f->codice_anonimo,
                    'ore' => $f->ore_settimanali,
                    'docente_unico' => $f->docente_unico,
                ])->all(),
                'docenti' => $c->assegnazioniSostegno->map(fn ($a) => [
                    'id' => $a->docente_id,
                    'ore' => $a->ore,
                ])->all(),
                'conteggio' => $c->conteggioSostegnoEffettivo(),
            ])->all();
    }

    /** Converte in int le stringhe numeriche, anche negli array annidati. */
    private function interi(mixed $valore): mixed
    {
        if (is_array($valore)) {
            return array_map(fn ($v) => $this->interi($v), $valore);
        }

        return is_string($valore) && preg_match('/^-?\d+$/', $valore) ? (int) $valore : $valore;
    }

    private function vincoli(): array
    {
        return Vincolo::attivi()->get()->map(function (Vincolo $v) {
            // I form salvano i numeri come stringhe (anche negli elenchi: ids dell'ambito, slot_ids): il solver li confronta con id interi,
            // quindi un id rimasto stringa farebbe ignorare il vincolo senza alcun errore.
            $parametri = $this->interi($v->parametri ?? []);
            if (array_key_exists('disciplina_id', $parametri)) {
                $parametri['disciplina'] = $parametri['disciplina_id'] ? Disciplina::query()->find($parametri['disciplina_id'])?->codice : null;
                unset($parametri['disciplina_id']);
            }

            return [
                'id' => $v->id,
                'tipo' => $v->tipo,
                'ambito' => ['livello' => $v->ambito_livello, 'ids' => $this->interi($v->ambito_ids ?? [])],
                'parametri' => $parametri,
                'severita' => $v->severita,
                'peso' => $v->peso,
                'attivo' => $v->attivo,
            ];
        })->all();
    }
}
