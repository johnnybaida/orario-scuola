<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avvisi_orario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orario_id')->constrained('orari')->cascadeOnDelete();
            $table->enum('tipo', ['errore', 'avviso']);
            $table->text('messaggio');
            $table->timestamp('creato_il')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avvisi_orario');
    }
};
