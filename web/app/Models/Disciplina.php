<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sede_id', 'codice', 'nome', 'classe_concorso', 'tipo_aula_richiesto', 'tipi_aula_extra', 'padre_id'])]
class Disciplina extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'discipline';

    protected function casts(): array
    {
        return ['tipi_aula_extra' => 'array'];
    }

    /** Tutti i tipi di aula in cui la disciplina può svolgersi: quello richiesto più gli altri ammessi (vuoto = aula della classe). */
    public function tipiAmmessi(): array
    {
        return array_values(array_unique(array_filter([$this->tipo_aula_richiesto, ...($this->tipi_aula_extra ?? [])])));
    }

    public function accettaTipo(string $tipo): bool
    {
        return in_array($tipo, $this->tipiAmmessi(), true);
    }

    /** Il primo tipo diventa quello richiesto, gli altri sono ammessi in più. */
    public function impostaTipiAmmessi(array $tipi): void
    {
        $tipi = array_values(array_unique(array_filter($tipi)));
        $this->update(['tipo_aula_richiesto' => $tipi[0] ?? null, 'tipi_aula_extra' => count($tipi) > 1 ? array_slice($tipi, 1) : null]);
    }

    public function aggiungiTipo(string $tipo): void
    {
        if (! $this->accettaTipo($tipo)) {
            $this->impostaTipiAmmessi([...$this->tipiAmmessi(), $tipo]);
        }
    }

    public function togliTipo(string $tipo): void
    {
        if ($this->accettaTipo($tipo)) {
            $this->impostaTipiAmmessi(array_diff($this->tipiAmmessi(), [$tipo]));
        }
    }

    /** Id delle discipline che ammettono almeno uno dei tipi indicati (per le lezioni che si contendono le stesse aule). */
    public static function idCheAmmettono(array $tipi): array
    {
        return self::query()->get()->filter(fn (self $d) => array_intersect($d->tipiAmmessi(), $tipi))->pluck('id')->all();
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function sottoDiscipline(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function righeQuadroOrario(): HasMany
    {
        return $this->hasMany(QuadroOrarioRiga::class);
    }

    public function cattedre(): HasMany
    {
        return $this->hasMany(Cattedra::class);
    }
}
