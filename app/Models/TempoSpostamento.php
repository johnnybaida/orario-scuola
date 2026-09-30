<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sede_a_id', 'sede_b_id', 'minuti'])]
class TempoSpostamento extends Model
{
    protected $table = 'tempi_spostamento';

    public function sedeA(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_a_id');
    }

    public function sedeB(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_b_id');
    }
}
