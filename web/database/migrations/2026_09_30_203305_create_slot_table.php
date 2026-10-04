<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('giorno');
            $table->unsignedTinyInteger('ordine');
            $table->time('inizio');
            $table->time('fine');
            $table->boolean('intervallo_dopo')->default(false);
            $table->timestamps();

            $table->unique(['giorno', 'ordine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot');
    }
};
