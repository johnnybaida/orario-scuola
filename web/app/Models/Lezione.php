<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orario_id', 'cattedra_id', 'slot_id', 'durata_slot', 'aula_id', 'bloccata', 'con_clil', 'docente_sostituto_id', 'clil_sostituto_id'])]
class Lezione extends Model
{
    use \App\Models\Concerns\PerSedeVia;

    public const SEDE_VIA = 'orario';

    use HasFactory;

    protected $table = 'lezioni';

    protected function casts(): array
    {
        return [
            'bloccata' => 'boolean',
            'con_clil' => 'boolean',
        ];
    }

    public function orario(): BelongsTo
    {
        return $this->belongsTo(Orario::class);
    }

    public function cattedra(): BelongsTo
    {
        return $this->belongsTo(Cattedra::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function docenteSostituto(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_sostituto_id');
    }

    public function clilSostituto(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'clil_sostituto_id');
    }

    /** Il docente che fa la lezione: il sostituto, se c'è, altrimenti il titolare della cattedra (richiede cattedra caricata). */
    public function docenteEffettivo(): Docente
    {
        return $this->docente_sostituto_id ? $this->docenteSostituto : $this->cattedra->docente;
    }

    /** Il docente CLIL della lezione (solo se la lezione è in compresenza CLIL): il sostituto o quello della cattedra. */
    public function docenteClilEffettivo(): ?Docente
    {
        if (! $this->con_clil) {
            return null;
        }

        return $this->clil_sostituto_id ? $this->clilSostituto : $this->cattedra->docenteClil;
    }

    /** Vero se il docente (o il CLIL) della lezione è stato sostituito rispetto alla cattedra. */
    public function sostituita(): bool
    {
        return (bool) ($this->docente_sostituto_id || ($this->con_clil && $this->clil_sostituto_id));
    }

    /**
     * L'aula da mostrare nelle griglie: quella assegnata, se è diversa dall'aula base della classe (che è ovvia).
     * Per le classi con aula fissa compare solo per palestra, laboratori...; in DADA, dove le classi non hanno aula
     * base, compare sempre. Richiede cattedra.classe caricata.
     */
    public function aulaDaMostrare(): ?Aula
    {
        return $this->aula && $this->aula_id !== $this->cattedra?->classe?->aula_base_id ? $this->aula : null;
    }

    /** Id dei docenti presenti nella lezione: il titolare e, se la lezione è in compresenza CLIL, il docente CLIL (richiede cattedra caricata). */
    public function docentiIds(): array
    {
        return array_values(array_filter([
            $this->docente_sostituto_id ?: $this->cattedra->docente_id,
            $this->con_clil ? ($this->clil_sostituto_id ?: $this->cattedra->docente_clil_id) : null,
        ]));
    }

    /** Lezioni in cui un docente è presente, come titolare o come docente CLIL. */
    public function scopeDelDocente($query, int $docenteId)
    {
        return $query->where(fn ($q) => $q->where('docente_sostituto_id', $docenteId)
            ->orWhere(fn ($q) => $q->whereNull('docente_sostituto_id')->whereHas('cattedra', fn ($c) => $c->where('docente_id', $docenteId)))
            ->orWhere(fn ($q) => $q->where('con_clil', true)->where(fn ($q) => $q->where('clil_sostituto_id', $docenteId)
                ->orWhere(fn ($q) => $q->whereNull('clil_sostituto_id')->whereHas('cattedra', fn ($c) => $c->where('docente_clil_id', $docenteId))))));
    }

    /** Lezioni che si svolgono in un'aula: quelle assegnate e quelle senza aula di una classe che la ha come aula base. */
    public function scopeInAula($query, Aula $aula)
    {
        return $query->where(fn ($q) => $q->where('aula_id', $aula->id)
            ->orWhere(fn ($q) => $q->whereNull('aula_id')->whereHas('cattedra.classe', fn ($c) => $c->where('aula_base_id', $aula->id))));
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }
}
