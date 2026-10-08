<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orario_id', 'cattedra_id', 'slot_id', 'durata_slot', 'aula_id', 'bloccata'])]
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

    /**
     * L'aula da mostrare nelle griglie: quella assegnata, se è diversa dall'aula base della classe (che è ovvia).
     * Per le classi con aula fissa compare solo per palestra, laboratori...; in DADA, dove le classi non hanno aula
     * base, compare sempre. Richiede cattedra.classe caricata.
     */
    public function aulaDaMostrare(): ?Aula
    {
        return $this->aula && $this->aula_id !== $this->cattedra?->classe?->aula_base_id ? $this->aula : null;
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
