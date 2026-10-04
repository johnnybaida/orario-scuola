<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Durata esplicita (in minuti) della ricreazione dopo un'ora; null = nessuna. intervallo_dopo resta in sincronia (lo usa il solver). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->unsignedSmallInteger('ricreazione_minuti')->nullable()->after('intervallo_dopo');
        });

        // Ricreazioni già impostate: la durata era lo spazio fino all'ora successiva dello stesso giorno (10' se non c'è).
        foreach (DB::table('slot')->where('intervallo_dopo', true)->get() as $s) {
            $prossima = DB::table('slot')->where('giorno', $s->giorno)->where('ordine', $s->ordine + 1)->first();
            $minuti = $prossima ? (int) round((strtotime($prossima->inizio) - strtotime($s->fine)) / 60) : 0;
            DB::table('slot')->where('id', $s->id)->update(['ricreazione_minuti' => $minuti > 0 ? $minuti : 10]);
        }
    }

    public function down(): void
    {
        Schema::table('slot', function (Blueprint $table) {
            $table->dropColumn('ricreazione_minuti');
        });
    }
};
