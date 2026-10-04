<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orario_id', 'docente_id', 'classe_id', 'slot_id', 'codice_anonimo'])]
class CompresenzaSostegno extends Model
{
    protected $table = 'compresenze_sostegno';

    public function orario(): BelongsTo
    {
        return $this->belongsTo(Orario::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
