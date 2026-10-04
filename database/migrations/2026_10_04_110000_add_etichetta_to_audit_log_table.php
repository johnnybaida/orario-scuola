<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nome leggibile dell'elemento al momento dell'operazione: resta consultabile anche dopo la sua eliminazione. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->string('etichetta')->nullable()->after('entita_id');
            $table->index(['entita', 'entita_id']);
            $table->index('creato_il');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['entita', 'entita_id']);
            $table->dropIndex(['creato_il']);
            $table->dropColumn('etichetta');
        });
    }
};
