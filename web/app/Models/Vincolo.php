<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sede_id', 
    'profilo_vincoli_id', 'tipo', 'ambito_livello', 'ambito_ids', 'parametri', 'severita', 'peso', 'attivo', 'nota',
])]
class Vincolo extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'vincoli';

    protected function casts(): array
    {
        return [
            'ambito_ids' => 'array',
            'parametri' => 'array',
            'attivo' => 'boolean',
        ];
    }

    public function profilo(): BelongsTo
    {
        return $this->belongsTo(ProfiloVincoli::class, 'profilo_vincoli_id');
    }

    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }
}
