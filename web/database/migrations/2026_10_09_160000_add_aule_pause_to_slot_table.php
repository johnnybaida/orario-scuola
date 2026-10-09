<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Aula (di tipo «pausa») in cui si svolge la ricreazione/mensa dopo l'ora e la pausa prima della prima ora: compare nei PDF. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ricreazione_aula_id', 'pausa_prima_aula_id'] as $colonna) {
            if (! Schema::hasColumn('slot', $colonna)) {
                Schema::table('slot', fn (Blueprint $table) => $table->foreignId($colonna)->nullable()->constrained('aule')->nullOnDelete());
            }
        }
    }

    public function down(): void
    {
        foreach (['ricreazione_aula_id', 'pausa_prima_aula_id'] as $colonna) {
            if (Schema::hasColumn('slot', $colonna)) {
                Schema::table('slot', function (Blueprint $table) use ($colonna) {
                    $table->dropConstrainedForeignId($colonna);
                });
            }
        }
    }
};
