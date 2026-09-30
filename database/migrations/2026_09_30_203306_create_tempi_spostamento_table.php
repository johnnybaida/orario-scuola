<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tempi_spostamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_a_id')->constrained('sedi')->cascadeOnDelete();
            $table->foreignId('sede_b_id')->constrained('sedi')->cascadeOnDelete();
            $table->unsignedSmallInteger('minuti');
            $table->timestamps();

            $table->unique(['sede_a_id', 'sede_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tempi_spostamento');
    }
};
