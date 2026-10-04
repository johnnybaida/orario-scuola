<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['orario_id', 'user_id', 'tipo', 'lezione_id', 'dati_prima', 'dati_dopo', 'annullata'])]
class ModificaOrario extends Model
{
    protected $table = 'modifiche_orario';

    protected function casts(): array
    {
        return ['dati_prima' => 'array', 'dati_dopo' => 'array', 'annullata' => 'boolean'];
    }

    /** Descrizione per i messaggi all'utente. */
    public function descrizione(): string
    {
        return match ($this->tipo) {
            'spostamento' => 'spostamento di una lezione',
            'scambio' => 'scambio di due lezioni',
            'cambio_cattedra' => 'cambio di docente o materia',
            'blocco' => 'blocco o sblocco di una lezione',
            default => $this->tipo,
        };
    }
}
