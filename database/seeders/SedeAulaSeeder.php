<?php

namespace Database\Seeders;

use App\Models\Aula;
use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedeAulaSeeder extends Seeder
{
    public function run(): void
    {
        $sede = Sede::query()->create([
            'nome' => 'Plesso Centrale',
            'indirizzo' => 'Via Roma 1',
        ]);

        // Un'aula base per ciascuna delle 15 classi.
        for ($i = 1; $i <= 15; $i++) {
            Aula::query()->create([
                'sede_id' => $sede->id,
                'nome' => "Aula {$i}",
                'tipo' => 'classe',
                'capienza' => 30,
            ]);
        }

        Aula::query()->create([
            'sede_id' => $sede->id,
            'nome' => 'Palestra',
            'tipo' => 'palestra',
            'capienza' => 2,
        ]);

        Aula::query()->create([
            'sede_id' => $sede->id,
            'nome' => 'Laboratorio Tecnologia',
            'tipo' => 'laboratorio',
            'capienza' => 1,
        ]);

        Aula::query()->create([
            'sede_id' => $sede->id,
            'nome' => 'Aula Musica',
            'tipo' => 'aula_musica',
            'capienza' => 1,
        ]);

        Aula::query()->create([
            'sede_id' => $sede->id,
            'nome' => 'Aula Alternativa IRC',
            'tipo' => 'aula_alternativa',
            'capienza' => 10,
        ]);
    }
}
