<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use App\Models\QuadroOrario;
use Illuminate\Database\Seeder;

/**
 * Quadro orario "tempo normale" da 30h settimanali (DPR 89/2009), vedi
 * docs/analisi-orario-scuola-media.md §5.3.
 */
class QuadroOrarioSeeder extends Seeder
{
    public function run(): void
    {
        $ore = [
            'ITA' => 6, 'STO' => 2, 'GEO' => 2,
            'MAT' => 4, 'SCI' => 2,
            'TEC' => 2, 'ING' => 3, 'FRA' => 2,
            'ART' => 2, 'MUS' => 2, 'MOT' => 2, 'IRC' => 1,
        ];

        $quadro = QuadroOrario::query()->create([
            'nome' => 'Tempo normale 30h',
            'ore_totali' => array_sum($ore),
        ]);

        foreach ($ore as $codice => $oreSettimanali) {
            $quadro->righe()->create([
                'disciplina_id' => Disciplina::query()->where('codice', $codice)->value('id'),
                'ore_settimanali' => $oreSettimanali,
            ]);
        }
    }
}
