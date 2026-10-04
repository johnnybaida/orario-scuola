<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['docente_id', 'dal', 'al', 'motivo', 'esclude_da_orario', 'note'])]
class Sospensione extends Model
{
    use \App\Models\Concerns\Auditable;

    protected $table = 'sospensioni';

    public const MOTIVI = [
        'sospensione' => 'Sospensione dal servizio',
        'malattia' => 'Malattia lunga',
        'congedo' => 'Congedo o aspettativa',
        'altro' => 'Altro',
    ];

    protected function casts(): array
    {
        return ['dal' => 'date', 'al' => 'date', 'esclude_da_orario' => 'boolean'];
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function attivaIl(CarbonInterface $data): bool
    {
        return $this->dal->startOfDay()->lte($data) && ($this->al === null || $this->al->endOfDay()->gte($data));
    }

    /** "dal 01/10/2026 al 15/10/2026" oppure "dal 01/10/2026 (fino a nuova comunicazione)". */
    public function periodo(): string
    {
        return 'dal '.$this->dal->format('d/m/Y').($this->al ? ' al '.$this->al->format('d/m/Y') : ' (fino a nuova comunicazione)');
    }

    public function etichettaMotivo(): string
    {
        return self::MOTIVI[$this->motivo] ?? $this->motivo;
    }

    public function etichettaAudit(): ?string
    {
        return trim(($this->docente?->nomeCompleto() ?? '').' – '.$this->etichettaMotivo().' '.$this->periodo());
    }
}
