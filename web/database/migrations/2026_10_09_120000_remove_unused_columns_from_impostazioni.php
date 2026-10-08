<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Durata dell'ora e giorni della settimana non erano usati da nessuna parte (si ricavano dalla scansione oraria). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['durata_ora_minuti', 'giorni_settimana'] as $colonna) {
            if (Schema::hasColumn('impostazioni', $colonna)) {
                Schema::table('impostazioni', fn (Blueprint $t) => $t->dropColumn($colonna));
            }
        }
    }

    public function down(): void
    {
        Schema::table('impostazioni', function (Blueprint $t) {
            if (! Schema::hasColumn('impostazioni', 'durata_ora_minuti')) {
                $t->unsignedSmallInteger('durata_ora_minuti')->default(50);
            }
            if (! Schema::hasColumn('impostazioni', 'giorni_settimana')) {
                $t->unsignedTinyInteger('giorni_settimana')->default(5);
            }
        });
    }
};
