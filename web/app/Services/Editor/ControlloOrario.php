<?php

namespace App\Services\Editor;

use App\Models\Cattedra;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use Illuminate\Support\Collection;

/**
 * Stato reale di un orario, ricalcolato a ogni richiesta (a differenza degli avvisi, che sono il registro degli esiti
 * delle modifiche): docente in due classi nello stesso slot, docente indisponibile, classe con due lezioni nello
 * stesso slot, slot fuori scansione, aule usate da troppe classi o mancanti, ore diverse dal quadro, ore senza lezione.
 */
class ControlloOrario
{
    /**
     * @return list<array{gravita: string, testo: string, lezioni: int[], classi: int[], docenti: int[]}> prima gli errori, poi gli avvisi
     */
    public function problemi(Orario $orario): array
    {
        $lezioni = Lezione::query()->where('orario_id', $orario->id)
            ->with('cattedra.classe.slotAttivi', 'cattedra.docente.indisponibilita', 'cattedra.disciplina', 'slot', 'aula')->get();
        $problemi = [
            ...$this->docentiInDuePosti($lezioni),
            ...$this->docentiIndisponibili($lezioni),
            ...$this->classiConDueLezioni($lezioni),
            ...$this->slotFuoriScansione($lezioni),
            ...$this->auleMancanti($lezioni),
            ...$this->auleDoppie($lezioni),
            ...$this->oreDiverseDalQuadro($lezioni),
            ...$this->oreSenzaLezione($lezioni),
            ...$this->laboratori($lezioni),
        ];

        usort($problemi, fn ($a, $b) => [$a['gravita'] === 'errore' ? 0 : 1] <=> [$b['gravita'] === 'errore' ? 0 : 1]);

        return $problemi;
    }

    /** I problemi che toccano una classe (anche solo per una lezione coinvolta). */
    public function perClasse(array $problemi, int $classeId): array
    {
        return array_values(array_filter($problemi, fn ($p) => in_array($classeId, $p['classi'])));
    }

    /**
     * @param  list<array{testo: string, lezioni: int[]}>  $problemi
     * @return array<int, string[]> id lezione => testi dei problemi che la riguardano (per colorare i riquadri)
     */
    public function mappaPerLezione(array $problemi): array
    {
        $mappa = [];
        foreach ($problemi as $problema) {
            foreach ($problema['lezioni'] as $id) {
                $mappa[$id][] = $problema['testo'];
            }
        }

        return $mappa;
    }

    /** I problemi che toccano un docente. */
    public function perDocente(array $problemi, int $docenteId): array
    {
        return array_values(array_filter($problemi, fn ($p) => in_array($docenteId, $p['docenti'])));
    }

    /** I problemi che coinvolgono almeno una delle lezioni indicate (per esempio quelle di un'aula). */
    public function perLezioni(array $problemi, array $lezioneIds): array
    {
        return array_values(array_filter($problemi, fn ($p) => array_intersect($p['lezioni'], $lezioneIds) !== []));
    }

    private function p(string $gravita, string $testo, Collection|array $lezioni, Collection|array $classi, Collection|array $docenti = []): array
    {
        return [
            'gravita' => $gravita, 'testo' => $testo, 'lezioni' => collect($lezioni)->values()->all(),
            'classi' => collect($classi)->unique()->values()->all(), 'docenti' => collect($docenti)->unique()->values()->all(),
        ];
    }

    private function docentiInDuePosti(Collection $lezioni): array
    {
        $problemi = [];
        $gruppi = $lezioni->groupBy(fn (Lezione $l) => $l->cattedra->docente_id.'-'.$l->slot_id)->filter(fn ($g) => $g->count() > 1);

        foreach ($gruppi as $gruppo) {
            if ($gruppo->every(fn (Lezione $l) => $l->cattedra->compresenza)) {
                continue; // compresenza dichiarata
            }
            $docente = $gruppo->first()->cattedra->docente;
            $dove = $gruppo->map(fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' ('.$l->cattedra->disciplina->nome.')')->implode(' e in ');
            $problemi[] = $this->p('errore', "{$docente->nomeCompleto()}, {$gruppo->first()->slot->descrizione()}: è in due posti, in {$dove}.",
                $gruppo->pluck('id'), $gruppo->map(fn (Lezione $l) => $l->cattedra->classe_id), [$docente->id]);
        }

        return $problemi;
    }

    private function docentiIndisponibili(Collection $lezioni): array
    {
        return $lezioni->filter(fn (Lezione $l) => $l->cattedra->docente->indisponibilita->contains('id', $l->slot_id))
            ->map(fn (Lezione $l) => $this->p('errore',
                "{$l->cattedra->classe->nomeCompleto()}, {$l->slot->descrizione()}: {$l->cattedra->docente->nomeCompleto()} ({$l->cattedra->disciplina->nome}) non è disponibile in quell'ora.",
                [$l->id], [$l->cattedra->classe_id], [$l->cattedra->docente_id]))->values()->all();
    }

    private function classiConDueLezioni(Collection $lezioni): array
    {
        $problemi = [];
        $gruppi = $lezioni->groupBy(fn (Lezione $l) => $l->cattedra->classe_id.'-'.$l->slot_id)->filter(fn ($g) => $g->count() > 1);

        foreach ($gruppi as $gruppo) {
            if ($gruppo->every(fn (Lezione $l) => $l->cattedra->compresenza)) {
                continue;
            }
            $classe = $gruppo->first()->cattedra->classe;
            $quali = $gruppo->map(fn (Lezione $l) => $l->cattedra->disciplina->nome.' ('.$l->cattedra->docente->nomeCompleto().')')->implode(' e ');
            $problemi[] = $this->p('errore', "{$classe->nomeCompleto()}, {$gruppo->first()->slot->descrizione()}: due lezioni nello stesso momento, {$quali}.",
                $gruppo->pluck('id'), [$classe->id], $gruppo->map(fn (Lezione $l) => $l->cattedra->docente_id));
        }

        return $problemi;
    }

    private function slotFuoriScansione(Collection $lezioni): array
    {
        return $lezioni->filter(fn (Lezione $l) => ! $l->cattedra->classe->slotAttivi->contains('id', $l->slot_id))
            ->map(fn (Lezione $l) => $this->p('errore',
                "{$l->cattedra->classe->nomeCompleto()}, {$l->slot->descrizione()}: {$l->cattedra->disciplina->nome} è in un'ora che non fa parte della scansione oraria della classe.",
                [$l->id], [$l->cattedra->classe_id], [$l->cattedra->docente_id]))->values()->all();
    }

    /** Una lezione che richiede un tipo di aula ma non ne ha una assegnata (o ne ha una di tipo diverso). */
    private function auleMancanti(Collection $lezioni): array
    {
        $problemi = [];

        foreach ($lezioni as $l) {
            $tipo = $l->cattedra->disciplina->tipo_aula_richiesto;
            if (! $tipo) {
                continue;
            }
            $dove = "{$l->cattedra->classe->nomeCompleto()}, {$l->slot->descrizione()}: {$l->cattedra->disciplina->nome}";
            if (! $l->aula) {
                $problemi[] = $this->p('errore', "{$dove} richiede un'aula di tipo '{$tipo}' ma non ne ha una assegnata.", [$l->id], [$l->cattedra->classe_id]);
            } elseif (! $l->cattedra->disciplina->accettaTipo($l->aula->tipo)) {
                $problemi[] = $this->p('errore', "{$dove} richiede un'aula di tipo '{$tipo}' ma è in {$l->aula->nome} (tipo '{$l->aula->tipo}').", [$l->id], [$l->cattedra->classe_id]);
            }
        }

        return $problemi;
    }

    /** La stessa aula usata da più classi di quante ne possa contenere (capienza = lezioni contemporanee). */
    private function auleDoppie(Collection $lezioni): array
    {
        $problemi = [];
        $gruppi = $lezioni->filter(fn (Lezione $l) => $l->aula)->groupBy(fn (Lezione $l) => $l->aula_id.'-'.$l->slot_id);

        foreach ($gruppi as $gruppo) {
            $aula = $gruppo->first()->aula;
            $classi = $gruppo->map(fn (Lezione $l) => $l->cattedra->classe)->unique('id');
            if ($classi->count() > $aula->capienza) {
                $elenco = $gruppo->map(fn (Lezione $l) => $l->cattedra->classe->nomeCompleto().' ('.$l->cattedra->disciplina->nome.')')->implode(' e ');
                $problemi[] = $this->p('errore', "{$aula->nome}, {$gruppo->first()->slot->descrizione()}: usata da {$classi->count()} classi insieme ({$elenco}) ma può ospitarne {$aula->capienza}.",
                    $gruppo->pluck('id'), $classi->pluck('id'));
            }
        }

        return $problemi;
    }

    /** Laboratori pomeridiani (assegnati a mano) in conflitto con le lezioni, tra loro o con le indisponibilità. */
    private function laboratori(Collection $lezioni): array
    {
        return array_map(fn (array $c) => $this->p('errore', $c['testo'], $c['lezioni'], $lezioni->whereIn('id', $c['lezioni'])->map(fn (Lezione $l) => $l->cattedra->classe_id), $c['docenti']),
            app(\App\Services\Laboratori::class)->conflitti($lezioni));
    }

    private function oreDiverseDalQuadro(Collection $lezioni): array
    {
        $problemi = [];
        $conteggi = $lezioni->countBy('cattedra_id');

        foreach (Cattedra::query()->with('classe', 'disciplina', 'docente')->get()->reject(fn (Cattedra $c) => $c->disciplina->senza_slot) as $cattedra) {
            $ore = $conteggi[$cattedra->id] ?? 0;
            if ($ore !== $cattedra->ore) {
                $problemi[] = $this->p('errore', "{$cattedra->classe->nomeCompleto()}: {$cattedra->disciplina->nome} ({$cattedra->docente->nomeCompleto()}) ha {$ore} ore invece delle {$cattedra->ore} previste.",
                    [], [$cattedra->classe_id], [$cattedra->docente_id]);
            }
        }

        return $problemi;
    }

    private function oreSenzaLezione(Collection $lezioni): array
    {
        $problemi = [];
        $occupati = $lezioni->groupBy(fn (Lezione $l) => $l->cattedra->classe_id)->map(fn ($g) => $g->pluck('slot_id')->all());

        foreach ($lezioni->pluck('cattedra.classe')->unique('id') as $classe) {
            foreach ($classe->slotAttivi->sortBy(['giorno', 'ordine']) as $slot) {
                if (! in_array($slot->id, $occupati[$classe->id] ?? [])) {
                    $problemi[] = $this->p('avviso', "{$classe->nomeCompleto()}, {$slot->descrizione()}: nessuna lezione.", [], [$classe->id]);
                }
            }
        }

        return $problemi;
    }
}
