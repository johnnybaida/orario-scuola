<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'inizio', 'fine'])]
class AnnoScolastico extends Model
{
    use HasFactory;

    protected $table = 'anni_scolastici';

    protected function casts(): array
    {
        return [
            'inizio' => AsDate::class,
            'fine' => AsDate::class,
        ];
    }

    public function periodi(): HasMany
    {
        return $this->hasMany(Periodo::class);
    }
}
