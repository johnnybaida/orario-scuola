<?php

namespace Database\Factories;

use App\Models\QuadroOrario;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuadroOrario> */
class QuadroOrarioFactory extends Factory
{
    protected $model = QuadroOrario::class;

    public function definition(): array
    {
        return [
            'nome' => 'Quadro '.$this->faker->unique()->word(),
            'ore_totali' => 30,
        ];
    }
}
