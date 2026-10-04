<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vincoli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profilo_vincoli_id')->nullable()->constrained('profili_vincoli')->nullOnDelete();
            $table->string('tipo');
            $table->enum('ambito_livello', ['globale', 'classe', 'docente', 'disciplina', 'aula']);
            $table->json('ambito_ids')->nullable();
            $table->json('parametri');
            $table->enum('severita', ['rigido', 'preferenziale']);
            $table->unsignedTinyInteger('peso')->nullable();
            $table->boolean('attivo')->default(true);
            $table->text('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vincoli');
    }
};
