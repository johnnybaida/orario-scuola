<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'entita', 'entita_id', 'azione', 'dati_prima', 'dati_dopo'])]
class AuditLog extends Model
{
    protected $table = 'audit_log';

    const CREATED_AT = 'creato_il';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'dati_prima' => 'array',
            'dati_dopo' => 'array',
            'creato_il' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
