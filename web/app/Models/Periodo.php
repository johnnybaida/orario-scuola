<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['anno_scolastico_id', 'nome', 'tipo', 'inizio', 'fine'])]
class Periodo extends Model
{
    use HasFactory;

    protected $table = 'periodi';

    protected function casts(): array
    {
        return [
            'inizio' => 'date',
            'fine' => 'date',
        ];
    }

    public function annoScolastico(): BelongsTo
    {
        return $this->belongsTo(AnnoScolastico::class);
    }

    public function orari(): HasMany
    {
        return $this->hasMany(Orario::class);
    }

    public function generazioni(): HasMany
    {
        return $this->hasMany(Generazione::class);
    }

    /** Periodo di lavoro corrente: nessuna UI di gestione anno scolastico/periodi
     * nell'MVP, quindi se non esiste ancora se ne crea uno di default. */
    public static function corrente(): self
    {
        $esistente = static::query()->latest('id')->first();
        if ($esistente) {
            return $esistente;
        }

        $anno = AnnoScolastico::query()->create([
            'nome' => now()->year.'/'.(now()->year + 1),
            'inizio' => now()->startOfYear(),
            'fine' => now()->endOfYear(),
        ]);

        return static::query()->create([
            'anno_scolastico_id' => $anno->id,
            'nome' => 'Provvisorio',
            'tipo' => 'provvisorio',
            'inizio' => now()->startOfYear(),
            'fine' => now()->endOfYear(),
        ]);
    }
}
