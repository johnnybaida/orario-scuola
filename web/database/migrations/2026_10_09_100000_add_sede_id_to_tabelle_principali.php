<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La scuola ha più sedi, ciascuna con i suoi docenti, discipline, quadri, scansione oraria, vincoli, laboratori, orari e
 * impostazioni. Classi e aule hanno già la sede. I dati esistenti vanno tutti nella prima sede (se non ce n'è nessuna e ci
 * sono dati, ne nasce una «Sede principale»); i docenti collegati a più sedi vanno nella prima di quelle. La colonna resta
 * nullable: la imposta sempre il modello (trait PerSede).
 *
 * Su MariaDB le modifiche di struttura non si annullano se la migrazione si interrompe: ogni passo controlla se è già
 * stato fatto, così si può rilanciare. Un indice unico che serve a una chiave esterna si può togliere solo dopo averne
 * creato uno nuovo che inizia con le stesse colonne.
 */
return new class extends Migration
{
    private const TABELLE = ['docenti', 'discipline', 'quadri_orari', 'vincoli', 'orari', 'generazioni', 'slot', 'impostazioni', 'laboratori'];

    /** tabella => [colonne dell'indice unico di prima, colonne del nuovo] */
    private const UNICI = [
        'orari' => [['periodo_id', 'versione'], ['periodo_id', 'sede_id', 'versione']],   // prima il nuovo: il vecchio serve alla chiave su periodo_id
        'slot' => [['giorno', 'ordine'], ['sede_id', 'giorno', 'ordine']],
        'discipline' => [['codice'], ['sede_id', 'codice']],
        'docenti' => [['email'], ['sede_id', 'email']],
    ];

    public function up(): void
    {
        foreach (self::TABELLE as $tabella) {
            if (! Schema::hasColumn($tabella, 'sede_id')) {
                Schema::table($tabella, fn (Blueprint $t) => $t->foreignId('sede_id')->nullable()->constrained('sedi')->cascadeOnDelete());
            }
        }
        if (! Schema::hasColumn('users', 'ultima_sede_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->foreignId('ultima_sede_id')->nullable()->constrained('sedi')->nullOnDelete());
        }

        $sedeId = DB::table('sedi')->orderBy('id')->value('id');
        if (! $sedeId && collect(self::TABELLE)->contains(fn ($t) => DB::table($t)->exists())) {
            $sedeId = DB::table('sedi')->insertGetId(['nome' => 'Sede principale', 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($sedeId) {
            foreach (self::TABELLE as $tabella) {
                DB::table($tabella)->whereNull('sede_id')->update(['sede_id' => $sedeId]);
            }
            // Docenti collegati a sedi diverse dalla prima: restano nella prima sede collegata.
            foreach (DB::table('docente_sede')->orderBy('sede_id')->get()->unique('docente_id') as $riga) {
                DB::table('docenti')->where('id', $riga->docente_id)->update(['sede_id' => $riga->sede_id]);
            }
        }

        // Unicità per sede: prima si crea il nuovo indice, poi si toglie il vecchio.
        foreach (self::UNICI as $tabella => [$vecchio, $nuovo]) {
            Schema::table($tabella, function (Blueprint $t) use ($tabella, $vecchio, $nuovo) {
                if (! Schema::hasIndex($tabella, $nuovo, 'unique')) {
                    $t->unique($nuovo);
                }
            });
            Schema::table($tabella, function (Blueprint $t) use ($tabella, $vecchio) {
                if (Schema::hasIndex($tabella, $vecchio, 'unique')) {
                    $t->dropUnique($vecchio);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNICI as $tabella => [$vecchio, $nuovo]) {
            Schema::table($tabella, function (Blueprint $t) use ($tabella, $vecchio) {
                if (! Schema::hasIndex($tabella, $vecchio, 'unique')) {
                    $t->unique($vecchio);
                }
            });
            Schema::table($tabella, function (Blueprint $t) use ($tabella, $nuovo) {
                if (Schema::hasIndex($tabella, $nuovo, 'unique')) {
                    $t->dropUnique($nuovo);
                }
            });
        }

        if (Schema::hasColumn('users', 'ultima_sede_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('ultima_sede_id'));
        }
        foreach (self::TABELLE as $tabella) {
            if (Schema::hasColumn($tabella, 'sede_id')) {
                Schema::table($tabella, fn (Blueprint $t) => $t->dropConstrainedForeignId('sede_id'));
            }
        }
    }
};
