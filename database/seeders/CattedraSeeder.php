<?php

namespace Database\Seeders;

use App\Models\Cattedra;
use App\Models\Disciplina;
use Illuminate\Database\Seeder;

/**
 * Assegna le cattedre con un round-robin sui pool di docenti per classe di
 * concorso (vedi DocenteSeeder::$pools). Lettere (Ita+Sto+Geo) e
 * Matematica+Scienze sono assegnate allo stesso docente per classe, come
 * nella prassi reale (§6 dell'analisi).
 */
class CattedraSeeder extends Seeder
{
    public function run(): void
    {
        $pools = DocenteSeeder::$pools;
        $classi = ClasseSeeder::$classi;
        $ore = QuadroOrarioSeeder::class; // solo per riferimento nei commenti

        $discipline = Disciplina::query()->get()->keyBy('codice');
        $oreQuadro = [
            'ITA' => 6, 'STO' => 2, 'GEO' => 2,
            'MAT' => 4, 'SCI' => 2,
            'TEC' => 2, 'ING' => 3, 'FRA' => 2,
            'ART' => 2, 'MUS' => 2, 'MOT' => 2, 'IRC' => 1,
        ];

        $gruppi = [
            'A022' => ['ITA', 'STO', 'GEO'],
            'A028' => ['MAT', 'SCI'],
        ];
        $singole = [
            'TEC' => 'A060', 'ING' => 'AB25', 'FRA' => 'AC25',
            'ART' => 'A001', 'MUS' => 'A030', 'MOT' => 'A049', 'IRC' => 'IRC',
        ];

        foreach ($classi as $i => $classeId) {
            foreach ($gruppi as $concorso => $codici) {
                $pool = $pools[$concorso];
                $docenteId = $pool[$i % count($pool)];

                foreach ($codici as $codice) {
                    Cattedra::query()->create([
                        'docente_id' => $docenteId,
                        'classe_id' => $classeId,
                        'disciplina_id' => $discipline[$codice]->id,
                        'ore' => $oreQuadro[$codice],
                        'compresenza' => false,
                    ]);
                }
            }

            foreach ($singole as $codice => $concorso) {
                $pool = $pools[$concorso];
                $docenteId = $pool[$i % count($pool)];

                Cattedra::query()->create([
                    'docente_id' => $docenteId,
                    'classe_id' => $classeId,
                    'disciplina_id' => $discipline[$codice]->id,
                    'ore' => $oreQuadro[$codice],
                    'compresenza' => false,
                ]);
            }
        }
    }
}
