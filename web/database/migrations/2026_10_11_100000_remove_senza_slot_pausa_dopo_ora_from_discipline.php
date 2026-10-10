<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Toglie il vecchio metodo della mensa («disciplina senza ora» collegata a una pausa): ora ci sono le pause in Scansione oraria, le ore di mensa nel quadro e la pagina Mensa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline', function (Blueprint $table) {
            $table->dropColumn(['senza_slot', 'pausa_dopo_ora']);
        });
    }

    public function down(): void
    {
        Schema::table('discipline', function (Blueprint $table) {
            $table->boolean('senza_slot')->default(false);
            $table->unsignedTinyInteger('pausa_dopo_ora')->nullable();
        });
    }
};
