<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orario_id', 'cattedra_id', 'slot_id', 'durata_slot', 'aula_id', 'bloccata'])]
class Lezione extends Model
{
    use HasFactory;

    protected $table = 'lezioni';

    protected function casts(): array
    {
        return [
            'bloccata' => 'boolean',
        ];
    }

    public function orario(): BelongsTo
    {
        return $this->belongsTo(Orario::class);
    }

    public function cattedra(): BelongsTo
    {
        return $this->belongsTo(Cattedra::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }
}
