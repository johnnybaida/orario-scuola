<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattedre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('discipline')->cascadeOnDelete();
            $table->unsignedTinyInteger('ore');
            $table->boolean('compresenza')->default(false);
            $table->timestamps();

            $table->unique(['docente_id', 'classe_id', 'disciplina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattedre');
    }
};
