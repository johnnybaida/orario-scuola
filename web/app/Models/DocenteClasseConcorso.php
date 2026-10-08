<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['docente_id', 'classe_concorso'])]
class DocenteClasseConcorso extends Model
{
    use \App\Models\Concerns\PerSedeVia;

    public const SEDE_VIA = 'docente';

    protected $table = 'docente_classe_concorso';

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }
}
