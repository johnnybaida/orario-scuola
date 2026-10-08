<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'indirizzo'])]
class Sede extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $table = 'sedi';

    public function aule(): HasMany
    {
        return $this->hasMany(Aula::class);
    }

    public function classi(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function docenti(): HasMany
    {
        return $this->hasMany(Docente::class);
    }

    public function tempiVerso(): HasMany
    {
        return $this->hasMany(TempoSpostamento::class, 'sede_a_id');
    }
}
