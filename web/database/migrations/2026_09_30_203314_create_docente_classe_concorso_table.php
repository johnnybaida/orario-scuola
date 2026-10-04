<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_classe_concorso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->string('classe_concorso');
            $table->timestamps();

            $table->unique(['docente_id', 'classe_concorso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_classe_concorso');
    }
};
