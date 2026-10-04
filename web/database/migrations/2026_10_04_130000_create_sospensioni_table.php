<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Periodi in cui un docente non presta servizio (sospensione, malattia lunga, congedo...). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sospensioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->date('dal');
            $table->date('al')->nullable(); // null = fino a nuova comunicazione
            $table->string('motivo');
            $table->boolean('esclude_da_orario')->default(true); // true = le cattedre vanno riassegnate prima di generare
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['docente_id', 'dal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sospensioni');
    }
};
