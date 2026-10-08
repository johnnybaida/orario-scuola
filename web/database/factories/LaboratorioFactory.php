<?php

namespace Database\Factories;

use App\Models\Laboratorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Laboratorio> */
class LaboratorioFactory extends Factory
{
    protected $model = Laboratorio::class;

    public function definition(): array
    {
        return ['nome' => 'Laboratorio '.$this->faker->unique()->word(), 'attivo' => true];
    }
}
