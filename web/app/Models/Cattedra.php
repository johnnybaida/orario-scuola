<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['docente_id', 'classe_id', 'disciplina_id', 'ore', 'compresenza', 'sospensione_id', 'docente_clil_id', 'ore_clil'])]
class Cattedra extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSedeVia;

    public const SEDE_VIA = 'classe';

    protected $table = 'cattedre';

    protected function casts(): array
    {
        return [
            'compresenza' => 'boolean',
        ];
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    /** Docente in compresenza (es. madrelingua CLIL) su `ore_clil` delle ore di questa cattedra. */
    public function docenteClil(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_clil_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function lezioni(): HasMany
    {
        return $this->hasMany(Lezione::class);
    }

    /** Dati del form con la compresenza CLIL ripulita: senza docente (o uguale al titolare) niente ore, e mai più ore della cattedra. */
    public static function normalizzaClil(array $dati): array
    {
        $clil = ! empty($dati['docente_clil_id']) && (int) $dati['docente_clil_id'] !== (int) ($dati['docente_id'] ?? 0) ? (int) $dati['docente_clil_id'] : null;
        $dati['docente_clil_id'] = $clil;
        $dati['ore_clil'] = $clil ? max(0, min((int) ($dati['ore_clil'] ?? 0), (int) ($dati['ore'] ?? 0))) : 0;

        return $dati;
    }

    public function etichettaAudit(): ?string
    {
        return collect([$this->disciplina?->nome, $this->docente?->nomeCompleto(), $this->classe?->nomeCompleto()])->filter()->implode(' – ');
    }
}
