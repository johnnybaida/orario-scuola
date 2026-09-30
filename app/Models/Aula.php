<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 'nome', 'tipo', 'capienza'])]
class Aula extends Model
{
    use HasFactory;

    protected $table = 'aule';

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function classiBase(): HasMany
    {
        return $this->hasMany(Classe::class, 'aula_base_id');
    }

    public function lezioni(): HasMany
    {
        return $this->hasMany(Lezione::class);
    }
}
