<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assegnazioni_sostegno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->unsignedTinyInteger('ore');
            $table->timestamps();

            $table->unique(['classe_id', 'docente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assegnazioni_sostegno');
    }
};
