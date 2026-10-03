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

    /** Tipi suggeriti in UI; il campo resta una stringa libera (vedi DADA in CLAUDE.md). */
    public const TIPI_BASE = ['classe', 'laboratorio', 'palestra', 'aula_musica', 'aula_sostegno', 'aula_alternativa'];

    /**
     * Tipi di aula selezionabili: base + quelli già censiti, con le aule che li usano
     * (tipo => etichetta). Usato dalle select che devono puntare a un tipo esistente.
     *
     * @return array<string, string>
     */
    public static function tipiConAule(): array
    {
        return self::query()->orderBy('nome')->get()->groupBy('tipo')
            ->map(fn ($aule, $tipo) => (str_starts_with($tipo, 'dada_') ? 'DADA · ' : '').ucfirst(str_replace('_', ' ', $tipo)).' ('.$aule->pluck('nome')->implode(', ').')')
            ->all();
    }

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
