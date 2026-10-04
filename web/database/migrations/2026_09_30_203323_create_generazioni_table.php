<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generazioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('periodi')->cascadeOnDelete();
            $table->foreignId('orario_id')->nullable()->constrained('orari')->nullOnDelete();
            $table->unsignedBigInteger('seed');
            $table->unsignedSmallInteger('time_limit_s')->default(120);
            $table->enum('stato', ['in_coda', 'in_corso', 'completata', 'infattibile', 'fallita', 'annullata'])->default('in_coda');
            $table->unsignedTinyInteger('progresso')->default(0);
            $table->json('diagnostica')->nullable();
            $table->foreignId('creato_da')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generazioni');
    }
};
