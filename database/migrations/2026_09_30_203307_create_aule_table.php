<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $table->string('nome');
            // Stringa libera (non enum): oltre ai tipi base (classe, laboratorio,
            // palestra, aula_musica, aula_sostegno, aula_alternativa), una scuola
            // DADA può censire un'aula per ogni disciplina con un tipo a piacere
            // (es. "dada_italiano"), collegata poi da Disciplina.tipo_aula_richiesto.
            $table->string('tipo', 50)->default('classe');
            $table->unsignedSmallInteger('capienza')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aule');
    }
};
