<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codice', 'nome', 'classe_concorso', 'tipo_aula_richiesto', 'padre_id'])]
class Disciplina extends Model
{
    use HasFactory;

    protected $table = 'discipline';

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function sottoDiscipline(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function righeQuadroOrario(): HasMany
    {
        return $this->hasMany(QuadroOrarioRiga::class);
    }

    public function cattedre(): HasMany
    {
        return $this->hasMany(Cattedra::class);
    }
}
