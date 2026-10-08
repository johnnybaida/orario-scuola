<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $daAssegnare = collect(self::TABELLE)->contains(fn ($t) => DB::table($t)->whereNull('sede_id')->exists());
            foreach (self::TABELLE as $tabella) {
                DB::table($tabella)->whereNull('sede_id')->update(['sede_id' => $sedeId]);
            }
            if ($daAssegnare) {
                $this->riunisciClassiEAule($sedeId);
            }
            // Docenti collegati a sedi diverse dalla prima: restano nella prima sede collegata.
            if (Schema::hasTable('docente_sede')) {
                foreach (DB::table('docente_sede')->orderBy('sede_id')->get()->unique('docente_id') as $riga) {
                    DB::table('docenti')->where('id', $riga->docente_id)->update(['sede_id' => $riga->sede_id]);
                }
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

    /**
     * Prima le sedi erano solo un'etichetta di classi e aule, mentre docenti, discipline, quadri e orari erano comuni: chi
     * aveva classi o aule in più sedi le ritrova tutte nella prima, così restano collegate ai loro docenti e orari. Una
     * sezione che si ripeterebbe (stesso anno e sezione in due sedi) prende il suffisso «-id della sede di origine».
     */
    private function riunisciClassiEAule(int $sedeId): void
    {
        foreach (DB::table('classi')->where('sede_id', '!=', $sedeId)->orderBy('id')->get() as $classe) {
            $sezione = $classe->sezione;
            if (DB::table('classi')->where('anno_corso', $classe->anno_corso)->where('sezione', $sezione)->where('sede_id', $sedeId)->exists()) {
                $sezione = mb_substr($sezione.'-'.$classe->sede_id, 0, 10);
            }
            DB::table('classi')->where('id', $classe->id)->update(['sede_id' => $sedeId, 'sezione' => $sezione]);
            Log::warning("Migrazione sedi: la classe {$classe->id} ({$classe->anno_corso}{$classe->sezione}) era nella sede {$classe->sede_id}: ora è nella sede {$sedeId} come «{$sezione}».");
        }
        $aule = DB::table('aule')->where('sede_id', '!=', $sedeId)->get();
        DB::table('aule')->where('sede_id', '!=', $sedeId)->update(['sede_id' => $sedeId]);
        foreach ($aule as $aula) {
            Log::warning("Migrazione sedi: l'aula {$aula->id} ({$aula->nome}) era nella sede {$aula->sede_id}: ora è nella sede {$sedeId}.");
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
