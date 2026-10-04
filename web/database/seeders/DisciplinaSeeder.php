<?php

namespace Database\Seeders;

use App\Models\Disciplina;
use Illuminate\Database\Seeder;

class DisciplinaSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = [
            ['codice' => 'ITA', 'nome' => 'Italiano', 'classe_concorso' => 'A022'],
            ['codice' => 'STO', 'nome' => 'Storia', 'classe_concorso' => 'A022', 'padre' => 'ITA'],
            ['codice' => 'GEO', 'nome' => 'Geografia', 'classe_concorso' => 'A022', 'padre' => 'ITA'],
            ['codice' => 'MAT', 'nome' => 'Matematica', 'classe_concorso' => 'A028'],
            ['codice' => 'SCI', 'nome' => 'Scienze', 'classe_concorso' => 'A028', 'padre' => 'MAT'],
            ['codice' => 'TEC', 'nome' => 'Tecnologia', 'classe_concorso' => 'A060', 'tipo_aula' => 'laboratorio'],
            ['codice' => 'ING', 'nome' => 'Inglese', 'classe_concorso' => 'AB25'],
            ['codice' => 'FRA', 'nome' => 'Francese (seconda lingua)', 'classe_concorso' => 'AC25'],
            ['codice' => 'ART', 'nome' => 'Arte e immagine', 'classe_concorso' => 'A001'],
            ['codice' => 'MUS', 'nome' => 'Musica', 'classe_concorso' => 'A030'],
            ['codice' => 'MOT', 'nome' => 'Scienze motorie e sportive', 'classe_concorso' => 'A049', 'tipo_aula' => 'palestra'],
            ['codice' => 'IRC', 'nome' => 'Religione cattolica', 'classe_concorso' => 'IRC'],
        ];

        $ids = [];

        foreach ($discipline as $d) {
            $model = Disciplina::query()->create([
                'codice' => $d['codice'],
                'nome' => $d['nome'],
                'classe_concorso' => $d['classe_concorso'],
                'tipo_aula_richiesto' => $d['tipo_aula'] ?? null,
            ]);
            $ids[$d['codice']] = $model->id;
        }

        foreach ($discipline as $d) {
            if (isset($d['padre'])) {
                Disciplina::query()->where('codice', $d['codice'])
                    ->update(['padre_id' => $ids[$d['padre']]]);
            }
        }
    }
}
