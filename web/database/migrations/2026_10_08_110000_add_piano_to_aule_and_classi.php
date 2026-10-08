<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Piano dell'edificio (0 = terra, negativo = interrato, vuoto = non indicato): serve al vincolo C5 sugli spostamenti tra piani. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['aule', 'classi'] as $tabella) {
            Schema::table($tabella, fn (Blueprint $table) => $table->smallInteger('piano')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['aule', 'classi'] as $tabella) {
            Schema::table($tabella, fn (Blueprint $table) => $table->dropColumn('piano'));
        }
    }
};
