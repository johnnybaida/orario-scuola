<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cronologia delle modifiche manuali di un orario, per annulla/ripeti su più livelli (l'audit log resta intatto). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifiche_orario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orario_id')->constrained('orari')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo'); // spostamento | scambio | cambio_cattedra | blocco
            $table->foreignId('lezione_id')->constrained('lezioni')->cascadeOnDelete();
            $table->json('dati_prima');
            $table->json('dati_dopo');
            $table->boolean('annullata')->default(false); // true = sullo stack "ripeti"
            $table->timestamps();

            $table->index(['orario_id', 'annullata']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modifiche_orario');
    }
};
