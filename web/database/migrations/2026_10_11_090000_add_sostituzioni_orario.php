<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sostituzioni di docenti dentro un orario (senza toccare le cattedre): `lezioni.docente_sostituto_id` / `clil_sostituto_id` sostituiscono il
 * titolare e il docente CLIL di una lezione; `compresenze_sostegno.docente_originale_id` ricorda il sostegno sostituito; `orari.origine_id` lega
 * una copia all'orario da cui è stata duplicata (per tornare all'originale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lezioni', function (Blueprint $table) {
            $table->foreignId('docente_sostituto_id')->nullable()->constrained('docenti')->nullOnDelete();
            $table->foreignId('clil_sostituto_id')->nullable()->constrained('docenti')->nullOnDelete();
        });
        Schema::table('compresenze_sostegno', fn (Blueprint $table) => $table->foreignId('docente_originale_id')->nullable()->constrained('docenti')->nullOnDelete());
        Schema::table('orari', fn (Blueprint $table) => $table->foreignId('origine_id')->nullable()->constrained('orari')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('orari', fn (Blueprint $table) => $table->dropConstrainedForeignId('origine_id'));
        Schema::table('compresenze_sostegno', fn (Blueprint $table) => $table->dropConstrainedForeignId('docente_originale_id'));
        Schema::table('lezioni', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_sostituto_id');
            $table->dropConstrainedForeignId('clil_sostituto_id');
        });
    }
};
