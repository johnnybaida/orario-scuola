<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Minuti con cui la pausa conta nel monte ore di chi la sorveglia, a scatti di 15 (null = la durata arrotondata per eccesso al quarto d'ora). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            if (! Schema::hasColumn('slot', 'ricreazione_conteggio')) {
                $table->unsignedSmallInteger('ricreazione_conteggio')->nullable();
            }
            if (! Schema::hasColumn('slot', 'pausa_prima_conteggio')) {
                $table->unsignedSmallInteger('pausa_prima_conteggio')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('slot', fn (Blueprint $table) => $table->dropColumn(['ricreazione_conteggio', 'pausa_prima_conteggio']));
    }
};
