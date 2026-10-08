<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un docente appartiene a una sola sede (`docenti.sede_id`): il collegamento a più sedi non serve più. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('docente_sede');
    }

    public function down(): void
    {
        Schema::create('docente_sede', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['docente_id', 'sede_id']);
        });
    }
};
