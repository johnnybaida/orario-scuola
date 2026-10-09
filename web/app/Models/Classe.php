<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'anno_corso', 'sezione', 'sede_id', 'aula_base_id', 'quadro_orario_id', 'tempo_scuola', 'n_alunni',
    'conteggio_sostegno', 'piano',
])]
class Classe extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'classi';

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function aulaBase(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_base_id');
    }

    public function quadroOrario(): BelongsTo
    {
        return $this->belongsTo(QuadroOrario::class);
    }

    public function slotAttivi(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'classe_slot');
    }

    public function cattedre(): HasMany
    {
        return $this->hasMany(Cattedra::class);
    }

    public function fabbisogniSostegno(): HasMany
    {
        return $this->hasMany(FabbisognoSostegno::class);
    }

    public function assegnazioniSostegno(): HasMany
    {
        return $this->hasMany(AssegnazioneSostegno::class);
    }

    public function conteggioSostegnoEffettivo(): string
    {
        return $this->conteggio_sostegno ?? Impostazioni::correnti()->conteggio_sostegno;
    }

    /**
     * Ore del quadro che non sono lezioni: le «ore di mensa» del quadro e le discipline «senza ora» (la seconda cattedra in compresenza non si somma).
     * Gli slot attivi devono essere le ore del quadro meno queste.
     */
    public function oreSenzaOra(): int
    {
        return (int) $this->cattedre()->with('disciplina')->get()
            ->filter(fn (Cattedra $c) => $c->disciplina->senza_slot && ! $c->compresenza)->sum('ore') + (int) $this->quadroOrario?->ore_mensa;
    }

    public function nomeCompleto(): string
    {
        return "{$this->anno_corso}ª {$this->sezione}";
    }
}
