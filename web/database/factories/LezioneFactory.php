<?php

namespace Database\Factories;

use App\Models\Cattedra;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lezione> */
class LezioneFactory extends Factory
{
    protected $model = Lezione::class;

    public function definition(): array
    {
        return [
            'orario_id' => Orario::factory(),
            'cattedra_id' => Cattedra::factory(),
            'slot_id' => Slot::factory(),
            'durata_slot' => 1,
            'aula_id' => null,
            'bloccata' => false,
        ];
    }
}
