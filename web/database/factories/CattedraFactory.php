<?php

namespace Database\Factories;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cattedra> */
class CattedraFactory extends Factory
{
    protected $model = Cattedra::class;

    public function definition(): array
    {
        return [
            'docente_id' => Docente::factory(),
            'classe_id' => Classe::factory(),
            'disciplina_id' => Disciplina::factory(),
            'ore' => $this->faker->numberBetween(1, 6),
            'compresenza' => false,
        ];
    }
}
