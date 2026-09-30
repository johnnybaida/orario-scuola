<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['periodo_id', 'versione', 'stato', 'seed', 'punteggio', 'creato_da'])]
class Orario extends Model
{
    use HasFactory;

    protected $table = 'orari';

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function lezioni(): HasMany
    {
        return $this->hasMany(Lezione::class);
    }

    public function creatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }

    public function avvisi(): HasMany
    {
        return $this->hasMany(AvvisoOrario::class)->latest('creato_il');
    }
}
