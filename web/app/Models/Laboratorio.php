<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['sede_id', 'nome', 'aula_id', 'n_partecipanti', 'attivo', 'note'])]
class Laboratorio extends Model
{
    use HasFactory, \App\Models\Concerns\Auditable, \App\Models\Concerns\PerSede;

    protected $table = 'laboratori';

    protected function casts(): array
    {
        return ['attivo' => 'boolean'];
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function docenti(): BelongsToMany
    {
        return $this->belongsToMany(Docente::class, 'laboratorio_docente', 'laboratorio_id', 'docente_id');
    }

    public function classi(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'laboratorio_classe', 'laboratorio_id', 'classe_id');
    }

    public function slot(): BelongsToMany
    {
        return $this->belongsToMany(Slot::class, 'laboratorio_slot', 'laboratorio_id', 'slot_id');
    }

    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }

    /** «Lun 7ª–8ª, Mer 7ª»: quando si svolge (richiede lo slot caricato). */
    public function quando(): string
    {
        return $this->slot->sortBy(fn (Slot $s) => $s->giorno * 100 + $s->ordine)->groupBy('giorno')
            ->map(fn ($ore, $giorno) => ucfirst(mb_strtolower(Slot::GIORNI_BREVI[$giorno] ?? $giorno)).' '.$this->intervalloOre($ore->pluck('ordine')->all()))
            ->implode(', ') ?: '—';
    }

    /** [7, 8, 9] => «7ª–9ª»; [7, 9] => «7ª, 9ª». */
    private function intervalloOre(array $ordini): string
    {
        sort($ordini);
        $gruppi = [];
        foreach ($ordini as $o) {
            if ($gruppi && end($gruppi)[1] === $o - 1) {
                $gruppi[array_key_last($gruppi)][1] = $o;
            } else {
                $gruppi[] = [$o, $o];
            }
        }

        return collect($gruppi)->map(fn ($g) => $g[0] === $g[1] ? "{$g[0]}ª" : "{$g[0]}ª–{$g[1]}ª")->implode(', ');
    }
}
