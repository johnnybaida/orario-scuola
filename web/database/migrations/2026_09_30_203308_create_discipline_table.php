<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline', function (Blueprint $table) {
            $table->id();
            $table->string('codice')->unique();
            $table->string('nome');
            $table->string('classe_concorso')->nullable();
            // Stringa libera, deve coincidere con un Aula.tipo esistente (vedi
            // create_aule_table). Supporta sia i tipi base sia un tipo dedicato
            // DADA (un'aula specifica per questa disciplina).
            $table->string('tipo_aula_richiesto', 50)->nullable();
            $table->foreignId('padre_id')->nullable()->constrained('discipline')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline');
    }
};
