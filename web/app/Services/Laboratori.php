<?php

namespace App\Services;

use App\Models\Docente;
use App\Models\Laboratorio;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Services\Editor\SpostamentiAula;
use Illuminate\Support\Collection;

/**
 * I laboratori occupano docenti e aule negli slot indicati, fuori dalla generazione: qui si ricavano le occupazioni
 * (per il solver), i conflitti con le lezioni di un orario e le ore libere per collocarne uno nuovo.
 */
class Laboratori
{
    /** @return Collection<int, array{laboratorio: Laboratorio, docente_id: int, slot_id: int, aula_id: ?int}> una riga per docente e slot */
    public function occupazioni(): Collection
    {
        return Laboratorio::query()->attivi()->with('docenti', 'slot')->get()
            ->flatMap(fn (Laboratorio $l) => $l->docenti->flatMap(fn ($d) => $l->slot->map(fn ($s) => [
                'laboratorio' => $l, 'docente_id' => $d->id, 'slot_id' => $s->id, 'aula_id' => $l->aula_id,
            ])))->values();
    }

    /** Per il contratto del solver: docente e aula occupati in uno slot. */
    public function perSolver(): array
    {
        return $this->occupazioni()->map(fn ($o) => ['docente' => $o['docente_id'], 'slot' => $o['slot_id'], 'aula' => $o['aula_id'], 'laboratorio' => $o['laboratorio']->id])->all();
    }

    /**
     * Conflitti dei laboratori con le lezioni dell'orario e tra loro.
     *
     * @param  Collection<int, Lezione>  $lezioni  con cattedra.classe.aulaBase, cattedra.docente, aula, slot
     * @return list<array{testo: string, lezioni: int[], docenti: int[]}>
     */
    public function conflitti(Collection $lezioni): array
    {
        $problemi = [];
        $laboratori = Laboratorio::query()->attivi()->with('docenti.indisponibilita', 'slot', 'aula')->get();
        $perDocenteSlot = $lezioni->groupBy(fn (Lezione $l) => $l->cattedra->docente_id.'-'.$l->slot_id);
        $perAulaSlot = $lezioni->filter(fn (Lezione $l) => SpostamentiAula::aulaEffettiva($l))->groupBy(fn (Lezione $l) => SpostamentiAula::aulaEffettiva($l)->id.'-'.$l->slot_id);
        $labPerAulaSlot = [];

        foreach ($laboratori as $lab) {
            foreach ($lab->slot as $slot) {
                $dove = $slot->descrizione();
                foreach ($lab->docenti as $docente) {
                    foreach ($perDocenteSlot[$docente->id.'-'.$slot->id] ?? [] as $lezione) {
                        $problemi[] = ['testo' => "{$docente->nomeCompleto()}, {$dove}: ha il laboratorio «{$lab->nome}» e la lezione di {$lezione->cattedra->disciplina->nome} in {$lezione->cattedra->classe->nomeCompleto()}.",
                            'lezioni' => [$lezione->id], 'docenti' => [$docente->id]];
                    }
                    if ($docente->indisponibilita->contains('id', $slot->id)) {
                        $problemi[] = ['testo' => "{$docente->nomeCompleto()}, {$dove}: laboratorio «{$lab->nome}» in un'ora in cui è indisponibile.", 'lezioni' => [], 'docenti' => [$docente->id]];
                    }
                }
                if ($lab->aula) {
                    $labPerAulaSlot[$lab->aula_id.'-'.$slot->id][] = [$lab, $slot];
                }
            }
        }

        // Laboratori e lezioni insieme nella stessa aula, oltre la capienza (ogni laboratorio vale un'occupante).
        foreach ($labPerAulaSlot as $chiave => $gruppo) {
            [$lab, $slot] = $gruppo[0];
            $inLezione = $perAulaSlot[$chiave] ?? collect();
            if ($inLezione->pluck('cattedra.classe_id')->unique()->count() + count($gruppo) > $lab->aula->capienza) {
                $nomi = collect($gruppo)->map(fn ($g) => "«{$g[0]->nome}»")->implode(' e ');
                $problemi[] = ['testo' => "{$lab->aula->nome}, {$slot->descrizione()}: occupata da {$nomi} e da ".$inLezione->count().' lezioni ma può ospitarne '.$lab->aula->capienza.'.',
                    'lezioni' => $inLezione->pluck('id')->all(), 'docenti' => []];
            }
        }

        // Lo stesso docente in due laboratori nello stesso slot.
        $doppi = $this->occupazioni()->groupBy(fn ($o) => $o['docente_id'].'-'.$o['slot_id'])->filter(fn ($g) => $g->pluck('laboratorio.id')->unique()->count() > 1);
        foreach ($doppi as $gruppo) {
            $docente = Docente::query()->find($gruppo->first()['docente_id']);
            $slot = Slot::query()->find($gruppo->first()['slot_id']);
            $problemi[] = ['testo' => "{$docente->nomeCompleto()}, {$slot->descrizione()}: è in due laboratori insieme ({$gruppo->pluck('laboratorio.nome')->unique()->implode(' e ')}).", 'lezioni' => [], 'docenti' => [$docente->id]];
        }

        return $problemi;
    }

    /** L'orario con cui confrontare i laboratori quando se ne colloca uno: il pubblicato più recente, altrimenti l'ultimo. */
    public function orarioDiRiferimento(): ?Orario
    {
        return Orario::query()->where('stato', 'pubblicato')->latest('id')->first() ?? Orario::query()->latest('id')->first();
    }

    /**
     * Per ogni ora pomeridiana, i motivi per cui docenti e aula indicati non sono liberi (vuoto = libero).
     *
     * @return array<int, list<string>> slot_id => motivi
     */
    public function disponibilita(array $docentiIds, ?int $aulaId, ?int $escludiLaboratorio = null): array
    {
        $docenti = Docente::query()->with('indisponibilita')->whereIn('id', $docentiIds)->get();
        $lezioni = ($orario = $this->orarioDiRiferimento())
            ? Lezione::query()->where('orario_id', $orario->id)->with('cattedra.classe.aulaBase', 'cattedra.docente', 'cattedra.disciplina', 'aula')->get() : collect();
        $altri = Laboratorio::query()->attivi()->with('docenti', 'slot')->when($escludiLaboratorio, fn ($q, $id) => $q->whereKeyNot($id))->get();
        $aula = $aulaId ? \App\Models\Aula::query()->find($aulaId) : null;

        $risultato = [];
        foreach (Slot::query()->where('ordine', '>', Slot::ULTIMA_ORA_MATTINA)->get() as $slot) {
            $motivi = [];
            foreach ($docenti as $d) {
                if ($d->indisponibilita->contains('id', $slot->id)) {
                    $motivi[] = "{$d->nomeCompleto()} è indisponibile";
                }
                foreach ($lezioni->filter(fn (Lezione $l) => $l->slot_id === $slot->id && $l->cattedra->docente_id === $d->id) as $l) {
                    $motivi[] = "{$d->nomeCompleto()} ha lezione in {$l->cattedra->classe->nomeCompleto()}";
                }
                foreach ($altri->filter(fn ($a) => $a->docenti->contains('id', $d->id) && $a->slot->contains('id', $slot->id)) as $a) {
                    $motivi[] = "{$d->nomeCompleto()} è nel laboratorio «{$a->nome}»";
                }
            }
            if ($aula) {
                $inLezione = $lezioni->filter(fn (Lezione $l) => $l->slot_id === $slot->id && SpostamentiAula::aulaEffettiva($l)?->id === $aula->id)->pluck('cattedra.classe_id')->unique()->count();
                $inLab = $altri->filter(fn ($a) => $a->aula_id === $aula->id && $a->slot->contains('id', $slot->id))->count();
                if ($inLezione + $inLab >= $aula->capienza) {
                    $motivi[] = "{$aula->nome} è occupata";
                }
            }
            $risultato[$slot->id] = $motivi;
        }

        return $risultato;
    }

    /** Minuti settimanali di laboratorio di un docente (ore di servizio a parte). */
    public function minuti(Docente $docente): int
    {
        return (int) Laboratorio::query()->attivi()->whereHas('docenti', fn ($q) => $q->whereKey($docente->id))->with('slot')->get()
            ->sum(fn (Laboratorio $l) => $l->slot->sum(fn (Slot $s) => Slot::minutiTra($s->inizio, $s->fine)));
    }

    /** @return list<string> «Lun 7ª–8ª · Latino (Aula 3)» per l'orario di un docente o di un'aula */
    public function elenco(?Docente $docente = null, ?int $aulaId = null): array
    {
        return Laboratorio::query()->attivi()->with('slot', 'aula')
            ->when($docente, fn ($q) => $q->whereHas('docenti', fn ($d) => $d->whereKey($docente->id)))
            ->when($aulaId, fn ($q) => $q->where('aula_id', $aulaId))
            ->orderBy('nome')->get()->map(fn (Laboratorio $l) => $l->quando().' · '.$l->nome.($l->aula ? " ({$l->aula->nome})" : ''))->all();
    }
}
