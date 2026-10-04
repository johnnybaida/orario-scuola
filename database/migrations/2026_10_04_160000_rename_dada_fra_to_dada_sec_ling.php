<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Il tipo aula DADA della seconda lingua si chiama «dada_sec_ling» (prima «dada_fra», dal codice del francese). */
return new class extends Migration
{
    public function up(): void
    {
        $this->rinomina('dada_fra', 'dada_sec_ling');
    }

    public function down(): void
    {
        $this->rinomina('dada_sec_ling', 'dada_fra');
    }

    private function rinomina(string $da, string $a): void
    {
        DB::table('aule')->where('tipo', $da)->update(['tipo' => $a]);
        DB::table('discipline')->where('tipo_aula_richiesto', $da)->update(['tipo_aula_richiesto' => $a]);
    }
};
