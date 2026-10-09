<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pausa (ordine dell'ora che la precede, 0 = prima della prima ora) in cui si svolge una disciplina «senza ora» come la mensa: serve per mostrarla nei PDF. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('discipline', 'pausa_dopo_ora')) {
            Schema::table('discipline', fn (Blueprint $table) => $table->unsignedTinyInteger('pausa_dopo_ora')->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('discipline', fn (Blueprint $table) => $table->dropColumn('pausa_dopo_ora'));
    }
};
