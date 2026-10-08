<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['docente_id', 'giorno', 'ordine'])]
class AssistenzaPausa extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'assistenze_pausa';

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function etichettaAudit(): ?string
    {
        return trim(($this->docente?->nomeCompleto() ?? '').' – '.(Slot::GIORNI[$this->giorno] ?? $this->giorno).', pausa dopo la '.$this->ordine.'ª ora');
    }
}
