<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['docente_id', 'classe_concorso'])]
class DocenteClasseConcorso extends Model
{
    protected $table = 'docente_classe_concorso';

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }
}
