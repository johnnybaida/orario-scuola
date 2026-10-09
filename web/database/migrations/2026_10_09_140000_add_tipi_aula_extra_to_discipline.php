<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tipi di aula ammessi in più rispetto a `tipo_aula_richiesto` (es. la propria aula DADA e un'aula DADA condivisa con altre discipline). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline', fn (Blueprint $table) => $table->json('tipi_aula_extra')->nullable());
    }

    public function down(): void
    {
        Schema::table('discipline', fn (Blueprint $table) => $table->dropColumn('tipi_aula_extra'));
    }
};
