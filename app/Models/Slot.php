<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['giorno', 'ordine', 'inizio', 'fine', 'intervallo_dopo'])]
class Slot extends Model
{
    use HasFactory;

    protected $table = 'slot';

    public const GIORNI = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato'];

    /** Le ore con ordine > 6 sono i rientri pomeridiani (stessa convenzione degli slot mattutini di default). */
    public const ULTIMA_ORA_MATTINA = 6;

    protected function casts(): array
    {
        return [
            'intervallo_dopo' => 'boolean',
        ];
    }

    public function classi(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_slot');
    }

    public function docentiIndisponibili(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'docente_indisponibilita');
    }

    public function lezioni(): HasMany
    {
        return $this->hasMany(Lezione::class);
    }
}
