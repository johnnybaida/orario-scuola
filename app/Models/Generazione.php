<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'periodo_id', 'orario_id', 'seed', 'time_limit_s', 'stato', 'progresso', 'diagnostica', 'creato_da',
])]
class Generazione extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable;

    protected $table = 'generazioni';

    protected function casts(): array
    {
        return [
            'diagnostica' => 'array',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function orario(): BelongsTo
    {
        return $this->belongsTo(Orario::class);
    }

    public function creatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }

    protected function auditEsclusi(): array
    {
        return ['progresso', 'diagnostica'];
    }

    public function etichettaAudit(): ?string
    {
        return 'Generazione #'.$this->id;
    }
}
