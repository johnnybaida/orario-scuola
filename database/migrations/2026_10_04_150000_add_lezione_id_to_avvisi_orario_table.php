<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** La lezione su cui si stava operando: la griglia evidenzia in errore il suo riquadro finché l'avviso resta aperto. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avvisi_orario', function (Blueprint $table) {
            $table->foreignId('lezione_id')->nullable()->after('orario_id')->constrained('lezioni')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('avvisi_orario', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lezione_id');
        });
    }
};
