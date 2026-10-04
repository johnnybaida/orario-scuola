<?php

namespace Database\Factories;

use App\Models\Disciplina;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Disciplina> */
class DisciplinaFactory extends Factory
{
    protected $model = Disciplina::class;

    public function definition(): array
    {
        return [
            'codice' => strtoupper($this->faker->unique()->lexify('???')),
            'nome' => $this->faker->unique()->word(),
            'classe_concorso' => 'A022',
        ];
    }
}
