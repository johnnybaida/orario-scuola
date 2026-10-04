<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'ore_totali'])]
class QuadroOrario extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $table = 'quadri_orari';

    public function righe(): HasMany
    {
        return $this->hasMany(QuadroOrarioRiga::class);
    }

    public function classi(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
