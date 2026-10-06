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

    public function test_esporta_e_reimporta_le_aule_saltando_le_presenti(): void
    {
        $sede = Sede::factory()->create(['nome' => 'Centrale']);
        Aula::factory()->create(['sede_id' => $sede->id, 'nome' => 'Lab1', 'tipo' => 'laboratorio_informatica', 'capienza' => 1]);

        $csv = $this->actingAs($this->admin())->get('/csv/aule')->streamedContent();
        $this->assertStringContainsString('Centrale;Lab1', $csv);

        $nuovo = $csv."Centrale;Lab2;palestra;2\nInesistente;Lab3;palestra;2\nCentrale;Lab4;palestra;0\n";
        $r = $this->post('/csv/aule/importa', ['file' => UploadedFile::fake()->createWithContent('aule.csv', $nuovo)]);

        $r->assertOk()->assertSee('Importate 1 righe')->assertSee('1 già presenti')->assertSee('Riga 4')->assertSee('Riga 5');
        $this->assertDatabaseCount('aule', 2);
    }

    public function test_import_riservato_a_chi_puo_modificare(): void
    {
        $ds = User::factory()->create(['ruolo' => 'ds']);

        $this->actingAs($ds)->get('/csv/aule')->assertOk();
        $this->actingAs($ds)->get('/csv/aule/importa')->assertForbidden();
        $this->actingAs($ds)->get('/csv/inesistente')->assertNotFound();
    }
}
