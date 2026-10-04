<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['docente_id', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_sede');
    }
};
