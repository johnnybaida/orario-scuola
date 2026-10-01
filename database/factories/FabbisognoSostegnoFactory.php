<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\FabbisognoSostegno;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FabbisognoSostegno> */
class FabbisognoSostegnoFactory extends Factory
{
    protected $model = FabbisognoSostegno::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'codice_anonimo' => strtoupper($this->faker->unique()->bothify('?#-S#')),
            'ore_settimanali' => $this->faker->numberBetween(4, 12),
            'docente_unico' => false,
        ];
    }
}
