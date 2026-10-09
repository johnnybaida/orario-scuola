<?php

namespace App\Models;

use App\Enums\TipoAula;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 'nome', 'tipo', 'capienza', 'piano'])]
class Aula extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'aule';

    /**
     * Tipi di aula selezionabili: base + quelli già censiti, con le aule che li usano
     * (tipo => etichetta). Usato dalle select che devono puntare a un tipo esistente.
     *
     * @return array<string, string>
     */
    /** «2° piano», «piano terra», «interrato»; breve: «P2», «PT», «P-1». Null se il piano non è indicato. */
    public function etichettaPiano(bool $breve = false): ?string
    {
        if ($this->piano === null) {
            return null;
        }

        return match (true) {
            $this->piano === 0 => $breve ? 'PT' : 'piano terra',
            $this->piano < 0 => $breve ? "P{$this->piano}" : ($this->piano === -1 ? 'interrato' : abs($this->piano).'° interrato'),
            default => $breve ? "P{$this->piano}" : "{$this->piano}° piano",
        };
    }

    /** «Human Lab (2° piano)»: il nome con il piano, se c'è (per i PDF). */
    public function nomeConPiano(bool $breve = false): string
    {
        $piano = $this->etichettaPiano($breve);

        return $piano ? "{$this->nome} ({$piano})" : $this->nome;
    }

    public static function tipiConAule(): array
    {
        return self::query()->orderBy('nome')->get()->groupBy('tipo')
            ->map(fn ($aule, $tipo) => TipoAula::etichettaDi($tipo).' ('.$aule->pluck('nome')->implode(', ').')')
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
