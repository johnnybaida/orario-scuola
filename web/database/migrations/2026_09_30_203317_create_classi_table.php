<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classi', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('anno_corso');
            $table->string('sezione');
            $table->foreignId('sede_id')->constrained('sedi')->cascadeOnDelete();
            $table->foreignId('aula_base_id')->nullable()->constrained('aule')->nullOnDelete();
            $table->foreignId('quadro_orario_id')->constrained('quadri_orari')->cascadeOnDelete();
            $table->enum('tempo_scuola', ['normale', 'prolungato'])->default('normale');
            $table->unsignedSmallInteger('n_alunni')->default(0);
            $table->timestamps();

            $table->unique(['anno_corso', 'sezione', 'sede_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classi');
    }
};
