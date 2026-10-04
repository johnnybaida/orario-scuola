<?php

namespace Database\Seeders;

use App\Models\Impostazioni;
use Illuminate\Database\Seeder;

class ImpostazioniSeeder extends Seeder
{
    public function run(): void
    {
        Impostazioni::query()->firstOrCreate([], [
            'durata_ora_minuti' => 50,
            'giorni_settimana' => 5,
            'conteggio_sostegno' => 'per_alunno',
        ]);
    }
}
