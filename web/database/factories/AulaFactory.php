<?php

namespace Database\Factories;

use App\Models\Aula;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Aula> */
class AulaFactory extends Factory
{
    protected $model = Aula::class;

    public function definition(): array
    {
        return [
            'sede_id' => fn () => app(\App\Services\SedeCorrente::class)->predefinita(),
            'nome' => 'Aula '.$this->faker->unique()->numberBetween(1, 200),
            'tipo' => 'classe',
            'capienza' => 30,
        ];
    }
}
