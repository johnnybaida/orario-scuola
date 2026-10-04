<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('ruolo', [
                'amministratore', 'ds', 'referente_orario', 'referente_sostituzioni', 'segreteria', 'docente',
            ])->default('docente')->after('email');
            $table->foreignId('docente_id')->nullable()->after('ruolo')
                ->constrained('docenti')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_id');
            $table->dropColumn('ruolo');
        });
    }
};
