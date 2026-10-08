<?php

namespace App\Models\Concerns;

use App\Services\SedeCorrente;
use Illuminate\Database\Eloquent\Builder;

/**
 * Modello senza `sede_id` proprio, che appartiene alla sede del genitore (costante SEDE_VIA = nome della relazione,
 * il cui modello usa PerSede): le interrogazioni vedono solo i record della sede corrente.
 */
trait PerSedeVia
{
    protected static function bootPerSedeVia(): void
    {
        static::addGlobalScope('sede', function (Builder $query) {
            if (app(SedeCorrente::class)->id() !== null) {
                $query->whereHas($query->getModel()::SEDE_VIA);
            }
        });
    }
}
