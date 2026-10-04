<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compresenze_sostegno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orario_id')->constrained('orari')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('slot')->cascadeOnDelete();
            // null in modalità "per_classe" (la presenza copre l'intera classe, non un alunno specifico).
            $table->string('codice_anonimo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compresenze_sostegno');
    }
};
