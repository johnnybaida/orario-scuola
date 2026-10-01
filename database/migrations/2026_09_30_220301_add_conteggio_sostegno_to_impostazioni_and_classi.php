<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impostazioni', function (Blueprint $table) {
            $table->enum('conteggio_sostegno', ['per_alunno', 'per_classe'])->default('per_alunno')->after('giorni_settimana');
        });

        Schema::table('classi', function (Blueprint $table) {
            // null = eredita il default di istituto da Impostazioni.
            $table->enum('conteggio_sostegno', ['per_alunno', 'per_classe'])->nullable()->after('n_alunni');
        });
    }

    public function down(): void
    {
        Schema::table('classi', function (Blueprint $table) {
            $table->dropColumn('conteggio_sostegno');
        });

        Schema::table('impostazioni', function (Blueprint $table) {
            $table->dropColumn('conteggio_sostegno');
        });
    }
};
