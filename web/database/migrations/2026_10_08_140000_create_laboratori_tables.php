<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Laboratori pomeridiani: fuori dal monte ore e dalla generazione, assegnati a mano; occupano docenti e aule negli slot indicati. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratori', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->foreignId('aula_id')->nullable()->constrained('aule')->nullOnDelete();
            $table->unsignedSmallInteger('n_partecipanti')->nullable();
            $table->boolean('attivo')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('laboratorio_docente', function (Blueprint $table) {
            $table->foreignId('laboratorio_id')->constrained('laboratori')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->primary(['laboratorio_id', 'docente_id']);
        });

        Schema::create('laboratorio_classe', function (Blueprint $table) {
            $table->foreignId('laboratorio_id')->constrained('laboratori')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            $table->primary(['laboratorio_id', 'classe_id']);
        });

        Schema::create('laboratorio_slot', function (Blueprint $table) {
            $table->foreignId('laboratorio_id')->constrained('laboratori')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('slot')->cascadeOnDelete();
            $table->primary(['laboratorio_id', 'slot_id']);
        });
    }

    public function down(): void
    {
        foreach (['laboratorio_slot', 'laboratorio_classe', 'laboratorio_docente', 'laboratori'] as $tabella) {
            Schema::dropIfExists($tabella);
        }
    }
};
