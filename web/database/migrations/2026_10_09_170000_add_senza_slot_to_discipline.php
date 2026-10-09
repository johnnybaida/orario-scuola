<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Disciplina che conta nel quadro orario e nel monte ore ma non occupa un'ora di lezione (es. la mensa): le sue cattedre non diventano lezioni. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('discipline', 'senza_slot')) {
            Schema::table('discipline', fn (Blueprint $table) => $table->boolean('senza_slot')->default(false));
        }
    }

    public function down(): void
    {
        Schema::table('discipline', fn (Blueprint $table) => $table->dropColumn('senza_slot'));
    }
};
