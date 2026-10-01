<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome', 'cognome', 'email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe',
])]
class Docente extends Model
{
    use HasFactory;

    protected $table = 'docenti';

    protected function casts(): array
    {
        return [
            'coe' => 'boolean',
        ];
    }

    public function classiConcorso(): HasMany
    {
        return $this->hasMany(DocenteClasseConcorso::class);
    }

    public function sedi(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'docente_sede');
    }

    public function indisponibilita(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'docente_indisponibilita');
    }

    public function cattedre(): HasMany
    {
        return $this->hasMany(Cattedra::class);
    }

    public function assegnazioniSostegno(): HasMany
    {
        return $this->hasMany(AssegnazioneSostegno::class);
    }

    public function utente(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function nomeCompleto(): string
    {
        return "{$this->cognome} {$this->nome}";
    }
}
