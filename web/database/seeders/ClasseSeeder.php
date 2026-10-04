<?php

namespace Database\Seeders;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use Illuminate\Database\Seeder;

/**
 * 15 classi (1ª-3ª, sezioni A-E), tutte a tempo normale (30h) sulla
 * scansione oraria mattutina lun-ven.
 */
class ClasseSeeder extends Seeder
{
    /** @var array<int> id delle classi create, nell'ordine, riusati da CattedraSeeder */
    public static array $classi = [];

    public function run(): void
    {
        $sede = Sede::query()->firstOrFail();
        $quadro = QuadroOrario::query()->firstOrFail();
        $aule = Aula::query()->where('tipo', 'classe')->orderBy('id')->get();
        $slotMattutini = Slot::query()->where('ordine', '<=', 6)->get();

        $sezioni = ['A', 'B', 'C', 'D', 'E'];
        $i = 0;

        foreach ([1, 2, 3] as $anno) {
            foreach ($sezioni as $sezione) {
                $classe = Classe::query()->create([
                    'anno_corso' => $anno,
                    'sezione' => $sezione,
                    'sede_id' => $sede->id,
                    'aula_base_id' => $aule[$i]->id,
                    'quadro_orario_id' => $quadro->id,
                    'tempo_scuola' => 'normale',
                    'n_alunni' => random_int(18, 27),
                ]);

                $classe->slotAttivi()->attach($slotMattutini->pluck('id'));

                self::$classi[] = $classe->id;
                $i++;
            }
        }
    }
}
