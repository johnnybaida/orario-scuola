<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('periodi')->cascadeOnDelete();
            $table->unsignedSmallInteger('versione');
            $table->enum('stato', ['bozza', 'in_revisione', 'approvato', 'pubblicato', 'archiviato'])->default('bozza');
            $table->unsignedBigInteger('seed')->nullable();
            $table->unsignedInteger('punteggio')->nullable();
            $table->foreignId('creato_da')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['periodo_id', 'versione']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orari');
    }
};
