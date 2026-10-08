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
}
