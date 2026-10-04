<?php

namespace Database\Factories;

use App\Models\Orario;
use App\Models\Periodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Orario> */
class OrarioFactory extends Factory
{
    protected $model = Orario::class;

    public function definition(): array
    {
        return [
            'periodo_id' => Periodo::factory(),
            'versione' => 1,
            'stato' => 'bozza',
            'seed' => 1,
            'punteggio' => 0,
        ];
    }
}
