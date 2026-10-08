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
    use HasFactory, \App\Models\Concerns\Auditable;

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

    public function sospensioni(): HasMany
    {
        return $this->hasMany(Sospensione::class)->orderBy('dal');
    }

    /** Sospensione in corso alla data indicata (oggi se omessa); usa la relazione caricata se c'è. */
    public function sospensioneAttiva(?\Carbon\CarbonInterface $data = null): ?Sospensione
    {
        $data ??= now();

        return $this->sospensioni->first(fn (Sospensione $s) => $s->attivaIl($data));
    }

    public function assistenzePausa(): HasMany
    {
        return $this->hasMany(AssistenzaPausa::class)->orderBy('giorno')->orderBy('ordine');
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
