<?php

namespace Database\Factories;

use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Docente> */
class DocenteFactory extends Factory
{
    protected $model = Docente::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->firstName(),
            'cognome' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'tipo_contratto' => 'tempo_indeterminato',
            'tipo_posto' => 'comune',
            'regime' => 'tempo_pieno',
            'ore_dovute' => 18,
            'coe' => false,
        ];
    }
}
