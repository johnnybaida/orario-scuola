<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sede_id', 'durata_ora_minuti', 'giorni_settimana', 'conteggio_sostegno'])]
class Impostazioni extends Model
{
    use \App\Models\Concerns\PerSede;

    protected $table = 'impostazioni';

    public static function correnti(): self
    {
        // Valori espliciti: dopo un INSERT, Eloquent non rilegge i default di
        // colonna sull'istanza in memoria.
        return static::query()->firstOrCreate([], [
            'durata_ora_minuti' => 50,
            'giorni_settimana' => 5,
            'conteggio_sostegno' => 'per_alunno',
        ]);
    }
}
