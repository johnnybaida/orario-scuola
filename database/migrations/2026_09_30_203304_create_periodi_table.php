<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anno_scolastico_id')->constrained('anni_scolastici')->cascadeOnDelete();
            $table->string('nome');
            $table->enum('tipo', ['provvisorio', 'definitivo'])->default('provvisorio');
            $table->date('inizio');
            $table->date('fine');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodi');
    }
};
