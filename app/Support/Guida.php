<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Collega le pagine dell'app alla sezione della guida (docs/guida-utente.md) che si apre per prima. */
class Guida
{
    /** Pattern del nome rotta => id della sezione (lo slug del titolo "##"). */
    public const MAPPA = [
        'dashboard' => 'per-iniziare',
        'sedi.*' => 'sedi-e-aule',
        'aule.*' => 'sedi-e-aule',
        'discipline.*' => 'discipline',
        'quadri-orari.*' => 'quadri-orari',
        'docenti.*' => 'docenti',
        'classi.*' => 'classi',
        'cattedre.*' => 'cattedre',
        'vincoli.*' => 'vincoli',
        'generazioni.*' => 'genera-orario',
        'orari.*' => 'orari-e-modifica-manuale',
        'utenze.*' => 'utenze',
    ];

    public static function sezionePer(?string $nomeRotta): ?string
    {
        foreach (self::MAPPA as $pattern => $sezione) {
            if ($nomeRotta && Str::is($pattern, $nomeRotta)) {
                return $sezione;
            }
        }

        return null;
    }
}
