<?php

namespace Database\Factories;

use App\Models\AnnoScolastico;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AnnoScolastico> */
class AnnoScolasticoFactory extends Factory
{
    protected $model = AnnoScolastico::class;

    public function definition(): array
    {
        return [
            'nome' => now()->year.'/'.(now()->year + 1),
            'inizio' => now()->startOfYear(),
            'fine' => now()->endOfYear(),
        ];
    }
}
