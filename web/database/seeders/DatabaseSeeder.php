<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database con una scuola di esempio realistica:
     * ~15 classi, ~40 docenti, quadro orario a 30h (vedi CLAUDE.md).
     */
    public function run(): void
    {
        // La scuola di esempio non è attività degli utenti: non va nell'audit log.
        \App\Models\AuditLog::senza(fn () => $this->call([
            SedeAulaSeeder::class,   // per prima: le sedi devono esistere prima dei dati che ne dipendono
            ImpostazioniSeeder::class,
            SlotSeeder::class,
            DisciplinaSeeder::class,
            QuadroOrarioSeeder::class,
            DocenteSeeder::class,
            ClasseSeeder::class,
            CattedraSeeder::class,
            UserSeeder::class,
        ]));
    }
}
