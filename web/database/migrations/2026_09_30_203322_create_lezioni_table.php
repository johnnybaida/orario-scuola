<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lezioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orario_id')->constrained('orari')->cascadeOnDelete();
            $table->foreignId('cattedra_id')->constrained('cattedre')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('slot')->cascadeOnDelete();
            $table->unsignedTinyInteger('durata_slot')->default(1);
            $table->foreignId('aula_id')->nullable()->constrained('aule')->nullOnDelete();
            $table->boolean('bloccata')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lezioni');
    }
};
