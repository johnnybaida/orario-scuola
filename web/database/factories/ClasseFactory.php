<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\QuadroOrario;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Classe> */
class ClasseFactory extends Factory
{
    protected $model = Classe::class;

    public function definition(): array
    {
        return [
            'anno_corso' => $this->faker->numberBetween(1, 3),
            'sezione' => $this->faker->unique()->randomLetter(),
            'sede_id' => Sede::factory(),
            'quadro_orario_id' => QuadroOrario::factory(),
            'tempo_scuola' => 'normale',
            'n_alunni' => $this->faker->numberBetween(18, 27),
        ];
    }
}
