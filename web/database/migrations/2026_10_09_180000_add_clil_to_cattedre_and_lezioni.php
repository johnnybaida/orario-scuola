<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Docente in compresenza (es. madrelingua CLIL) su una parte delle ore di una cattedra; `lezioni.con_clil` marca le lezioni in cui è presente. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cattedre', 'docente_clil_id')) {
            Schema::table('cattedre', fn (Blueprint $table) => $table->foreignId('docente_clil_id')->nullable()->constrained('docenti')->nullOnDelete());
        }
        if (! Schema::hasColumn('cattedre', 'ore_clil')) {
            Schema::table('cattedre', fn (Blueprint $table) => $table->unsignedTinyInteger('ore_clil')->default(0));
        }
        if (! Schema::hasColumn('lezioni', 'con_clil')) {
            Schema::table('lezioni', fn (Blueprint $table) => $table->boolean('con_clil')->default(false));
        }
    }

    public function down(): void
    {
        Schema::table('lezioni', fn (Blueprint $table) => $table->dropColumn('con_clil'));
        Schema::table('cattedre', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_clil_id');
            $table->dropColumn('ore_clil');
        });
    }
};
