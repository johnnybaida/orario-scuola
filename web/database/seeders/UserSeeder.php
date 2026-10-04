<?php

namespace Database\Seeders;

use App\Models\Docente;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $ruoli = [
            'amministratore' => 'Amministratore Sistema',
            'ds' => 'Dirigente Scolastico',
            'referente_orario' => 'Referente Orario',
            'referente_sostituzioni' => 'Referente Sostituzioni',
            'segreteria' => 'Segreteria',
        ];

        foreach ($ruoli as $ruolo => $nome) {
            User::query()->create([
                'name' => $nome,
                'email' => "{$ruolo}@scuola.test",
                'password' => 'password',
                'ruolo' => $ruolo,
            ]);
        }

        $primoDocente = Docente::query()->orderBy('id')->first();
        if ($primoDocente) {
            User::query()->create([
                'name' => $primoDocente->nomeCompleto(),
                'email' => 'docente@scuola.test',
                'password' => 'password',
                'ruolo' => 'docente',
                'docente_id' => $primoDocente->id,
            ]);
        }
    }
}
