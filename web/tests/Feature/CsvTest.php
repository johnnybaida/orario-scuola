<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['ruolo' => 'amministratore']);
    }

    public function test_esporta_e_reimporta_le_aule_saltando_le_presenti_e_solo_della_sede_corrente(): void
    {
        $centrale = Sede::factory()->create(['nome' => 'Centrale']);
        $altra = Sede::factory()->create(['nome' => 'Succursale']);
        Aula::factory()->create(['sede_id' => $centrale->id, 'nome' => 'Lab1', 'tipo' => 'laboratorio_informatica', 'capienza' => 1]);
        Aula::factory()->create(['sede_id' => $altra->id, 'nome' => 'AulaAltrove', 'tipo' => 'palestra', 'capienza' => 1]);

        $csv = $this->actingAs($this->admin())->get('/csv/aule')->streamedContent();
        $this->assertStringContainsString('Lab1;laboratorio_informatica;1', $csv);
        $this->assertStringNotContainsString('AulaAltrove', $csv);   // l'esportazione riguarda la sede in cui si lavora

        $nuovo = $csv."Lab2;palestra;2\nLab3;palestra;0\n";
        $r = $this->post('/csv/aule/importa', ['file' => UploadedFile::fake()->createWithContent('aule.csv', $nuovo)]);

        $r->assertOk()->assertSee('Importate 1 righe')->assertSee('1 già presenti')->assertSee('Riga 4');
        $this->assertDatabaseCount('aule', 3);
        $this->assertDatabaseHas('aule', ['nome' => 'Lab2', 'sede_id' => app(\App\Services\SedeCorrente::class)->id()]);
    }

    public function test_import_riservato_a_chi_puo_modificare(): void
    {
        $ds = User::factory()->create(['ruolo' => 'ds']);

        $this->actingAs($ds)->get('/csv/aule')->assertOk();
        $this->actingAs($ds)->get('/csv/aule/importa')->assertForbidden();
        $this->actingAs($ds)->get('/csv/inesistente')->assertNotFound();
    }

    public function test_esporta_scansione_e_quadri(): void
    {
        $quadro = \App\Models\QuadroOrario::factory()->create(['nome' => 'Normale 30h', 'ore_totali' => 30]);
        $disciplina = \App\Models\Disciplina::factory()->create(['codice' => 'ITA']);
        \App\Models\QuadroOrarioRiga::query()->create(['quadro_orario_id' => $quadro->id, 'disciplina_id' => $disciplina->id, 'ore_settimanali' => 6]);
        \App\Models\Slot::query()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'intervallo_dopo' => false]);
        $admin = $this->admin();

        $this->assertStringContainsString('"Normale 30h";30;ITA;6', $this->actingAs($admin)->get('/csv/quadri-orari')->streamedContent());
        $this->assertStringContainsString('1;08:00;08:50;', $this->actingAs($admin)->get('/csv/scansione')->streamedContent());
    }

    private function carica(string $lista, string $csv)
    {
        return $this->post("/csv/{$lista}/importa", ['file' => UploadedFile::fake()->createWithContent("{$lista}.csv", $csv)]);
    }

    public function test_importa_quadri_per_intero_o_per_niente_e_ricalcola_le_ore(): void
    {
        \App\Models\Disciplina::factory()->create(['codice' => 'ITA']);
        \App\Models\Disciplina::factory()->create(['codice' => 'MAT']);
        \App\Models\QuadroOrario::factory()->create(['nome' => 'Esistente']);
        $this->actingAs($this->admin());

        $r = $this->carica('quadri-orari', "quadro;ore_totali;disciplina;ore_settimanali\n"
            ."Buono;99;ITA;6\nBuono;99;MAT;4\n"
            ."Rotto;;ITA;6\nRotto;;XXX;2\n"
            ."Doppio;;ITA;6\nDoppio;;ITA;2\n"
            ."Esistente;;ITA;6\n");

        $r->assertOk()->assertSee('Importate 2 righe')->assertSee('«XXX» non trovata', false)->assertSee('Quadro «Doppio»');
        $this->assertSame(10, \App\Models\QuadroOrario::query()->where('nome', 'Buono')->value('ore_totali'));
        $this->assertDatabaseMissing('quadri_orari', ['nome' => 'Rotto']);
        $this->assertDatabaseMissing('quadri_orari', ['nome' => 'Doppio']);
    }

    public function test_importa_la_scansione_su_tutti_i_giorni_e_rifiuta_orari_incoerenti(): void
    {
        foreach ([1, 2] as $giorno) {
            foreach ([1, 2] as $ordine) {
                \App\Models\Slot::query()->create(['giorno' => $giorno, 'ordine' => $ordine, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'intervallo_dopo' => false]);
            }
        }
        $this->actingAs($this->admin());

        $this->carica('scansione', "ora;inizio;fine;ricreazione_minuti\n1;8:00;08:50;10\n2;09:10;10:00;\n")->assertOk()->assertSee('Importate 2 righe');
        $this->assertDatabaseHas('slot', ['giorno' => 2, 'ordine' => 2, 'inizio' => '09:10:00']);
        $this->assertDatabaseHas('slot', ['giorno' => 1, 'ordine' => 1, 'ricreazione_minuti' => 10]);

        // ricreazione che sfora l'inizio dell'ora dopo, e ore mancanti: non cambia nulla
        $this->carica('scansione', "ora;inizio;fine;ricreazione_minuti\n1;08:00;08:50;30\n2;09:10;10:00;\n")->assertSee('finisce dopo');
        $this->carica('scansione', "ora;inizio;fine;ricreazione_minuti\n1;07:00;07:50;\n")->assertSee('tutte le ore');
        $this->assertDatabaseHas('slot', ['ordine' => 1, 'inizio' => '08:00:00']);
    }
}
