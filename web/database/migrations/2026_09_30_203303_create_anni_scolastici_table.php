<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anni_scolastici', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->date('inizio');
            $table->date('fine');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anni_scolastici');
    }
};
