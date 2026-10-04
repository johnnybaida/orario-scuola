<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quadro_orario_righe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quadro_orario_id')->constrained('quadri_orari')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('discipline')->cascadeOnDelete();
            $table->unsignedSmallInteger('ore_settimanali');
            $table->timestamps();

            $table->unique(['quadro_orario_id', 'disciplina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quadro_orario_righe');
    }
};
