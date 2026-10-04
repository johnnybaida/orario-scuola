<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impostazioni', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('durata_ora_minuti')->default(50);
            $table->unsignedTinyInteger('giorni_settimana')->default(6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impostazioni');
    }
};
