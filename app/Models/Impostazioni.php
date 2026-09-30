<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['durata_ora_minuti', 'giorni_settimana'])]
class Impostazioni extends Model
{
    protected $table = 'impostazioni';

    public static function correnti(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
