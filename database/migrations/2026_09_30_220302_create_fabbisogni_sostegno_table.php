<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabbisogni_sostegno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            // Alunno non censito: solo un codice anonimo (es. "1B-S1", vedi §5.6).
            $table->string('codice_anonimo');
            $table->unsignedTinyInteger('ore_settimanali');
            // S4: tutte le ore di questo fabbisogno a un solo docente di sostegno.
            $table->boolean('docente_unico')->default(false);
            $table->timestamps();

            $table->unique(['classe_id', 'codice_anonimo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabbisogni_sostegno');
    }
};
