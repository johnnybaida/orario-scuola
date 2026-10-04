<?php

namespace Database\Factories;

use App\Models\AnnoScolastico;
use App\Models\Periodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Periodo> */
class PeriodoFactory extends Factory
{
    protected $model = Periodo::class;

    public function definition(): array
    {
        return [
            'anno_scolastico_id' => AnnoScolastico::factory(),
            'nome' => 'Provvisorio',
            'tipo' => 'provvisorio',
            'inizio' => now()->startOfYear(),
            'fine' => now()->endOfYear(),
        ];
    }
}
