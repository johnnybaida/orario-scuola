<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['classe_id', 'docente_id', 'ore'])]
class AssegnazioneSostegno extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $table = 'assegnazioni_sostegno';

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function etichettaAudit(): ?string
    {
        return collect([$this->docente?->nomeCompleto(), $this->classe?->nomeCompleto()])->filter()->implode(' – ');
    }
}
