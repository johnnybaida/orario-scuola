<?php

namespace Database\Seeders;

use App\Models\Impostazioni;
use Illuminate\Database\Seeder;

class ImpostazioniSeeder extends Seeder
{
    public function run(): void
    {
        Impostazioni::query()->firstOrCreate([], [
            'conteggio_sostegno' => 'per_alunno',
        ]);
    }
}
