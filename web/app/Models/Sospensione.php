<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Services\Substitution\SostituzioneCattedre;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['docente_id', 'dal', 'al', 'motivo', 'esclude_da_orario', 'note'])]
class Sospensione extends Model
{
    use \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSedeVia;

    public const SEDE_VIA = 'docente';

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

    protected static function booted(): void
    {
        // Togliere la sospensione = il titolare è di nuovo in servizio: le cattedre ai supplenti tornano a lui.
        static::deleting(fn (self $s) => app(SostituzioneCattedre::class)->ripristina($s));
    }

    public function supplenti(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'sospensione_supplente', 'sospensione_id', 'docente_id');
    }

    /** Cattedre del titolare passate ai supplenti per questa sospensione. */
    public function cattedreSostituite(): HasMany
    {
        return $this->hasMany(Cattedra::class);
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
