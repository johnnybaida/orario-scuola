<?php

namespace Database\Factories;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Slot> */
class SlotFactory extends Factory
{
    protected $model = Slot::class;

    public function definition(): array
    {
        return [
            'giorno' => 1,
            'ordine' => $this->faker->unique()->numberBetween(1, 6),
            'inizio' => '08:00:00',
            'fine' => '08:50:00',
            'intervallo_dopo' => false,
        ];
    }
}
