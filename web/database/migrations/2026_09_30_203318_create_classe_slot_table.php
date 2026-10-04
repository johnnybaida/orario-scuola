<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classe_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classi')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('slot')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['classe_id', 'slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classe_slot');
    }
};
