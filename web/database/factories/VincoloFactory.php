<?php

namespace Database\Factories;

use App\Models\Vincolo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vincolo> */
class VincoloFactory extends Factory
{
    protected $model = Vincolo::class;

    public function definition(): array
    {
        return [
            'tipo' => 'T3_MAX_ORE_BUCHE',
            'ambito_livello' => 'globale',
            'ambito_ids' => [],
            'parametri' => ['max_per_giorno' => 2],
            'severita' => 'preferenziale',
            'peso' => 30,
            'attivo' => true,
        ];
    }
}
