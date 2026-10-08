<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrazioneSedeTest extends TestCase
{
    use RefreshDatabase;

    /** Su MariaDB una migrazione interrotta a metà non si annulla: deve potersi rilanciare senza errori. */
    public function test_la_migrazione_delle_sedi_si_puo_rilanciare(): void
    {
        $migrazione = require database_path('migrations/2026_10_09_100000_add_sede_id_to_tabelle_principali.php');

        $migrazione->up();
        $migrazione->up();

        $this->assertTrue(Schema::hasColumn('docenti', 'sede_id'));
        $this->assertTrue(Schema::hasIndex('orari', ['periodo_id', 'sede_id', 'versione'], 'unique'));
        $this->assertFalse(Schema::hasIndex('orari', ['periodo_id', 'versione'], 'unique'));
    }

    public function test_chi_aveva_classi_in_piu_sedi_le_ritrova_tutte_nella_prima_collegate_ai_loro_docenti(): void
    {
        $n = now();
        $migrazione = require database_path('migrations/2026_10_09_100000_add_sede_id_to_tabelle_principali.php');
        $prima = \App\Models\Sede::factory()->create(['nome' => 'Centrale']);
        $seconda = \App\Models\Sede::factory()->create(['nome' => 'Succursale']);
        $quadro = \DB::table('quadri_orari')->insertGetId(['nome' => 'Q', 'ore_totali' => 30, 'created_at' => $n, 'updated_at' => $n]);
        $classe = fn ($sede, $sezione) => \DB::table('classi')->insertGetId(['anno_corso' => 1, 'sezione' => $sezione, 'sede_id' => $sede, 'quadro_orario_id' => $quadro,
            'tempo_scuola' => 'normale', 'n_alunni' => 20, 'created_at' => $n, 'updated_at' => $n]);
        $inPrima = $classe($prima->id, 'A');
        $inSeconda = $classe($seconda->id, 'A');        // stessa sezione in un'altra sede
        $altra = $classe($seconda->id, 'B');
        \DB::table('aule')->insert(['sede_id' => $seconda->id, 'nome' => 'Aula S', 'tipo' => 'classe', 'capienza' => 1, 'created_at' => $n, 'updated_at' => $n]);
        \DB::table('docenti')->insert(['nome' => 'N', 'cognome' => 'Rossi', 'tipo_contratto' => 'tempo_indeterminato', 'tipo_posto' => 'comune', 'regime' => 'tempo_pieno',
            'ore_dovute' => 18, 'coe' => 0, 'sede_id' => null, 'created_at' => $n, 'updated_at' => $n]);   // dato «vecchio», senza sede

        $migrazione->up();

        $this->assertSame([$prima->id], \DB::table('classi')->pluck('sede_id')->unique()->values()->all());
        $this->assertSame([$prima->id], \DB::table('aule')->pluck('sede_id')->unique()->values()->all());
        $this->assertSame('A', \DB::table('classi')->where('id', $inPrima)->value('sezione'));
        $this->assertSame('A-'.$seconda->id, \DB::table('classi')->where('id', $inSeconda)->value('sezione'));   // sezione ripetuta: suffisso
        $this->assertSame('B', \DB::table('classi')->where('id', $altra)->value('sezione'));
        $this->assertSame($prima->id, (int) \DB::table('docenti')->value('sede_id'));
    }
}
