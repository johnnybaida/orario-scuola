<?php

use App\Constraints\DisciplineVincolo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** I vincoli sulle discipline valgono per un elenco di discipline (`disciplina_ids`) invece che per una sola (`disciplina_id`). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('vincoli')->get(['id', 'parametri']) as $v) {
            $p = json_decode((string) $v->parametri, true);
            if (is_array($p) && array_key_exists('disciplina_id', $p)) {
                DB::table('vincoli')->where('id', $v->id)->update(['parametri' => json_encode(DisciplineVincolo::normalizza($p))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('vincoli')->get(['id', 'parametri']) as $v) {
            $p = json_decode((string) $v->parametri, true);
            if (is_array($p) && array_key_exists('disciplina_ids', $p)) {
                $ids = $p['disciplina_ids'];
                unset($p['disciplina_ids']);
                if (count($ids) === 1) {
                    $p['disciplina_id'] = (string) $ids[0];
                }   // più discipline: non si possono rappresentare, il vincolo resta valido per tutte le lezioni (solo ambito docente) o va ricreato
                DB::table('vincoli')->where('id', $v->id)->update(['parametri' => json_encode($p)]);
            }
        }
    }
};
