<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['orario_id', 'lezione_id', 'tipo', 'messaggio'])]
class AvvisoOrario extends Model
{
    protected $table = 'avvisi_orario';

    const CREATED_AT = 'creato_il';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'creato_il' => 'datetime',
        ];
    }

    public function orario(): BelongsTo
    {
        return $this->belongsTo(Orario::class);
    }
}
