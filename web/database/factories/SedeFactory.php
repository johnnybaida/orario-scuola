<?php

namespace Database\Factories;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sede> */
class SedeFactory extends Factory
{
    protected $model = Sede::class;

    public function definition(): array
    {
        return [
            'nome' => 'Plesso '.$this->faker->unique()->city(),
            'indirizzo' => $this->faker->streetAddress(),
        ];
    }
}
