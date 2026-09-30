<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['anno_scolastico_id', 'nome', 'tipo', 'inizio', 'fine'])]
class Periodo extends Model
{
    use HasFactory;

    protected $table = 'periodi';

    protected function casts(): array
    {
        return [
            'inizio' => AsDate::class,
            'fine' => AsDate::class,
        ];
    }

    public function annoScolastico(): BelongsTo
    {
        return $this->belongsTo(AnnoScolastico::class);
    }

    public function orari(): HasMany
    {
        return $this->hasMany(Orario::class);
    }

    public function generazioni(): HasMany
    {
        return $this->hasMany(Generazione::class);
    }
}
