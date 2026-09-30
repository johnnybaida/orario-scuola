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
            $table->enum('tipo_aula_richiesto', [
                'classe', 'laboratorio', 'palestra', 'aula_musica', 'aula_sostegno', 'aula_alternativa',
            ])->nullable();
            $table->foreignId('padre_id')->nullable()->constrained('discipline')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline');
    }
};
