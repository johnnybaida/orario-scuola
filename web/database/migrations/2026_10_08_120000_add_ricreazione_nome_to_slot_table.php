<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nome della pausa che segue l'ora (es. «Mensa»); vuoto = «Ricreazione». */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot', fn (Blueprint $table) => $table->string('ricreazione_nome', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('slot', fn (Blueprint $table) => $table->dropColumn('ricreazione_nome'));
    }
};
