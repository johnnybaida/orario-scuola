<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplenti indicati su una sospensione e, per ogni cattedra passata a un supplente, la sospensione che l'ha originata (per riportarla al titolare). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sospensione_supplente', function (Blueprint $table) {
            $table->foreignId('sospensione_id')->constrained('sospensioni')->cascadeOnDelete();
            $table->foreignId('docente_id')->constrained('docenti')->cascadeOnDelete();
            $table->primary(['sospensione_id', 'docente_id']);
        });

        Schema::table('cattedre', function (Blueprint $table) {
            $table->foreignId('sospensione_id')->nullable()->constrained('sospensioni')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cattedre', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sospensione_id');
        });
        Schema::dropIfExists('sospensione_supplente');
    }
};
