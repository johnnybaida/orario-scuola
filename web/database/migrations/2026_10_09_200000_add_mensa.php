<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensa come pausa: una pausa della scansione si marca «mensa»; il quadro orario dichiara le ore di mensa a parte; le assistenze
 * a una pausa possono indicare quali classi sorvegliano (nessuna = tutte le classi in mensa quel giorno).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('slot', 'ricreazione_mensa')) {
            Schema::table('slot', fn (Blueprint $table) => $table->boolean('ricreazione_mensa')->default(false));
        }
        if (! Schema::hasColumn('quadri_orari', 'ore_mensa')) {
            Schema::table('quadri_orari', fn (Blueprint $table) => $table->unsignedTinyInteger('ore_mensa')->default(0));
        }
        if (! Schema::hasTable('assistenza_pausa_classe')) {
            Schema::create('assistenza_pausa_classe', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assistenza_pausa_id')->constrained('assistenze_pausa')->cascadeOnDelete();
                $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
                $table->unique(['assistenza_pausa_id', 'classe_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistenza_pausa_classe');
        Schema::table('quadri_orari', fn (Blueprint $table) => $table->dropColumn('ore_mensa'));
        Schema::table('slot', fn (Blueprint $table) => $table->dropColumn('ricreazione_mensa'));
    }
};
