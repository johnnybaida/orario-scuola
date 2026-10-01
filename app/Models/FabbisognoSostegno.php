<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['classe_id', 'codice_anonimo', 'ore_settimanali', 'docente_unico'])]
class FabbisognoSostegno extends Model
{
    use HasFactory;

    protected $table = 'fabbisogni_sostegno';

    protected function casts(): array
    {
        return [
            'docente_unico' => 'boolean',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }
}
