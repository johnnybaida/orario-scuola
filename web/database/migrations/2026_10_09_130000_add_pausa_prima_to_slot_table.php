<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pausa prima della prima ora (es. accoglienza): minuti e nome, sulle ore con ordine 1; finisce all'inizio dell'ora. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->unsignedSmallInteger('pausa_prima_minuti')->nullable();
            $table->string('pausa_prima_nome', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('slot', fn (Blueprint $table) => $table->dropColumn(['pausa_prima_minuti', 'pausa_prima_nome']));
    }
};
