<?php

namespace App\Models;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Impostazioni di una sede (una riga per sede): oggi solo il conteggio predefinito del sostegno. */
#[Fillable(['sede_id', 'conteggio_sostegno'])]
class Impostazioni extends Model
{
    use \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'impostazioni';

    public const CONTEGGI = ['per_alunno' => 'Per alunno', 'per_classe' => 'Per classe'];

    public static function correnti(): self
    {
        // Valori espliciti: dopo un INSERT, Eloquent non rilegge i default di colonna sull'istanza in memoria.
        // La riga nasce da sola alla prima lettura: non è un'azione dell'utente, quindi non va nel registro.
        return AuditLog::senza(fn () => static::query()->firstOrCreate([], ['conteggio_sostegno' => 'per_alunno']));
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function etichettaAudit(): ?string
    {
        return 'Impostazioni della sede '.($this->sede?->nome ?? '');
    }
}
