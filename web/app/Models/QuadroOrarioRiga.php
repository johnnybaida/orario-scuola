<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quadro_orario_id', 'disciplina_id', 'ore_settimanali'])]
class QuadroOrarioRiga extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'quadro_orario_righe';

    public function quadroOrario(): BelongsTo
    {
        return $this->belongsTo(QuadroOrario::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function etichettaAudit(): ?string
    {
        return $this->disciplina?->nome.' – '.$this->ore_settimanali.' ore';
    }
}
