<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_esporta_lorario_di_una_classe_in_pdf(): void
    {
        $classe = Classe::factory()->create();
        $slot = Slot::factory()->create();
        $classe->slotAttivi()->attach($slot->id);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/classe/{$classe->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_esporta_lorario_di_un_docente_in_pdf(): void
    {
        $docente = Docente::factory()->create();
        $orario = Orario::factory()->create();
        Cattedra::factory()->create(['docente_id' => $docente->id]);

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/docente/{$docente->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_esporta_il_tabellone_generale_in_pdf(): void
    {
        $classe = Classe::factory()->create();
        $orario = Orario::factory()->create();

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/generale");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
