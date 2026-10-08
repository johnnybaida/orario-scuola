<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Docenti che sorvegliano gli alunni in una pausa (es. la mensa): per giorno e per pausa (= ora dopo cui cade). Anagrafica, non legata a un orario. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistenze_pausa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->unsignedTinyInteger('giorno');
            $table->unsignedTinyInteger('ordine'); // ordine dell'ora che precede la pausa (slot.ordine)
            $table->timestamps();

            $table->unique(['docente_id', 'giorno', 'ordine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistenze_pausa');
    }
};
