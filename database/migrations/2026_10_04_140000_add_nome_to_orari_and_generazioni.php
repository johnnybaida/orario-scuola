<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nome facoltativo dell'orario (es. «Settimana uscita didattica»); la generazione lo porta all'orario che produce. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orari', fn (Blueprint $t) => $t->string('nome', 120)->nullable()->after('versione'));
        Schema::table('generazioni', fn (Blueprint $t) => $t->string('nome', 120)->nullable()->after('orario_id'));
    }

    public function down(): void
    {
        Schema::table('orari', fn (Blueprint $t) => $t->dropColumn('nome'));
        Schema::table('generazioni', fn (Blueprint $t) => $t->dropColumn('nome'));
    }
};
