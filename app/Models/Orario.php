<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['periodo_id', 'versione', 'nome', 'stato', 'seed', 'punteggio', 'creato_da'])]
class Orario extends Model
{
    use HasFactory;

    protected $table = 'orari';

    /** Solo una bozza si modifica; negli altri stati l'orario è in sola lettura (si può duplicare). */
    public function modificabile(): bool
    {
        return $this->stato === 'bozza';
    }

    /** Nome dato dall'utente o, in mancanza, «Orario vN». */
    public function etichetta(): string
    {
        return $this->nome ?: 'Orario v'.$this->versione;
    }

    public function etichettaStato(): string
    {
        return \App\Support\StatiOrario::ETICHETTE[$this->stato] ?? $this->stato;
    }

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

    public function compresenzeSostegno(): HasMany
    {
        return $this->hasMany(CompresenzaSostegno::class);
    }
}
