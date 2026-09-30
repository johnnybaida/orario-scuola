<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docenti', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cognome');
            $table->string('email')->nullable()->unique();
            $table->enum('tipo_contratto', [
                'tempo_indeterminato', 'tempo_determinato_annuale', 'tempo_determinato_fino_termine', 'supplenza_breve',
            ])->default('tempo_indeterminato');
            $table->enum('tipo_posto', ['comune', 'sostegno', 'potenziamento', 'irc', 'strumento'])->default('comune');
            $table->enum('regime', [
                'tempo_pieno', 'part_time_orizzontale', 'part_time_verticale', 'part_time_misto',
            ])->default('tempo_pieno');
            $table->unsignedTinyInteger('ore_dovute')->default(18);
            $table->boolean('coe')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docenti');
    }
};
