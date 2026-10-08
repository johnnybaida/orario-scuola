<?php

namespace App\Models\Concerns;

use App\Services\SedeCorrente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Modello con campo `sede_id`: le interrogazioni vedono solo la sede corrente e i nuovi record vi vengono assegnati. */
trait PerSede
{
    protected static function bootPerSede(): void
    {
        static::addGlobalScope('sede', function (Builder $query) {
            if (($sede = app(SedeCorrente::class)->id()) !== null) {
                $query->where($query->getModel()->getTable().'.sede_id', $sede);
            }
        });
        static::creating(function (Model $modello) {
            $modello->sede_id ??= app(SedeCorrente::class)->predefinita();
        });
    }
}
