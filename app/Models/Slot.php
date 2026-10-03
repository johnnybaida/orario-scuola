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

    /** Sigle dei giorni per etichettare gli slot (es. LUN-3ª). */
    public const GIORNI_BREVI = [1 => 'LUN', 2 => 'MAR', 3 => 'MER', 4 => 'GIO', 5 => 'VEN', 6 => 'SAB'];

    /** Le ore con ordine > 6 sono i rientri pomeridiani (stessa convenzione degli slot mattutini di default). */
    public const ULTIMA_ORA_MATTINA = 6;

    /** Slot raggruppati per giorno, fino all'ora $ordineMax (null = tutte): niente righe vuote oltre l'ultima ora usata. */
    public static function perGiorno(?int $ordineMax = null)
    {
        return self::query()
            ->when($ordineMax, fn ($q) => $q->where('ordine', '<=', $ordineMax))
            ->orderBy('giorno')->orderBy('ordine')->get()->groupBy('giorno');
    }

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
