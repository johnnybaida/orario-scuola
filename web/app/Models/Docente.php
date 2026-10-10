<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 
    'nome', 'cognome', 'email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe',
])]
class Docente extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

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

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
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

    /** Cattedre in cui il docente è in compresenza CLIL. */
    public function cattedreClil(): HasMany
    {
        return $this->hasMany(Cattedra::class, 'docente_clil_id');
    }

    public function assegnazioniSostegno(): HasMany
    {
        return $this->hasMany(AssegnazioneSostegno::class);
    }

    public function utente(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Ore assegnate: cattedre + sostegno + compresenze CLIL + assistenza alle pause (60 minuti conteggiati = 1 ora); le cattedre «senza ora»
     * non si sommano se il docente ha assistenze (la mensa conta una volta sola). Richiede i withSum dell'elenco docenti
     * (cattedre_sum_ore, assegnazioni_sostegno_sum_ore, cattedre_clil_sum_ore_clil, ore_senza_ora) e `assistenzePausa` caricata.
     */
    public function oreAssegnate(\App\Services\AssistenzaPause $assistenza): float
    {
        return round((int) $this->cattedre_sum_ore + (int) $this->assegnazioni_sostegno_sum_ore + (int) $this->cattedre_clil_sum_ore_clil
            + $assistenza->ore($this) - $assistenza->oreCattedreDaEscludere($this), 2);
    }

    public function nomeCompleto(): string
    {
        return "{$this->cognome} {$this->nome}";
    }
}
