<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'indirizzo'])]
class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedi';

    public function aule(): HasMany
    {
        return $this->hasMany(Aula::class);
    }

    public function classi(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function docenti(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'docente_sede');
    }

    public function tempiVerso(): HasMany
    {
        return $this->hasMany(TempoSpostamento::class, 'sede_a_id');
    }
}
