<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['docente_id', 'classe_id', 'disciplina_id', 'ore', 'compresenza'])]
class Cattedra extends Model
{
    use HasFactory;

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
}
