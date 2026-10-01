<?php

namespace Database\Factories;

use App\Models\AssegnazioneSostegno;
use App\Models\Classe;
use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AssegnazioneSostegno> */
class AssegnazioneSostegnoFactory extends Factory
{
    protected $model = AssegnazioneSostegno::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'docente_id' => Docente::factory(['tipo_posto' => 'sostegno']),
            'ore' => $this->faker->numberBetween(4, 12),
        ];
    }
}
