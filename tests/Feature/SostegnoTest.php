<?php

namespace Tests\Feature;

use App\Models\AssegnazioneSostegno;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\FabbisognoSostegno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SostegnoTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_apre_la_scheda_classe_con_il_pannello_sostegno(): void
    {
        $classe = Classe::factory()->create();

        $response = $this->actingAs($this->referente())->get("/classi/{$classe->id}/edit");

        $response->assertOk()->assertSee('Sostegno');
    }

    public function test_aggiunge_un_fabbisogno_di_sostegno(): void
    {
        $classe = Classe::factory()->create();

        $response = $this->actingAs($this->referente())->post("/classi/{$classe->id}/sostegno/fabbisogni", [
            'codice_anonimo' => '1B-S1',
            'ore_settimanali' => 9,
            'docente_unico' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('fabbisogni_sostegno', [
            'classe_id' => $classe->id, 'codice_anonimo' => '1B-S1', 'ore_settimanali' => 9, 'docente_unico' => true,
        ]);
    }

    public function test_non_accetta_due_fabbisogni_con_lo_stesso_codice_nella_stessa_classe(): void
    {
        $classe = Classe::factory()->create();
        FabbisognoSostegno::factory()->create(['classe_id' => $classe->id, 'codice_anonimo' => '1B-S1']);

        $response = $this->actingAs($this->referente())->post("/classi/{$classe->id}/sostegno/fabbisogni", [
            'codice_anonimo' => '1B-S1',
            'ore_settimanali' => 5,
        ]);

        $response->assertSessionHasErrors('codice_anonimo');
    }

    public function test_rimuove_un_fabbisogno(): void
    {
        $classe = Classe::factory()->create();
        $fabbisogno = FabbisognoSostegno::factory()->create(['classe_id' => $classe->id]);

        $response = $this->actingAs($this->referente())
            ->delete("/classi/{$classe->id}/sostegno/fabbisogni/{$fabbisogno->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('fabbisogni_sostegno', ['id' => $fabbisogno->id]);
    }

    public function test_assegna_un_docente_di_sostegno_alla_classe(): void
    {
        $classe = Classe::factory()->create();
        $docente = Docente::factory()->create(['tipo_posto' => 'sostegno']);

        $response = $this->actingAs($this->referente())->post("/classi/{$classe->id}/sostegno/assegnazioni", [
            'docente_id' => $docente->id,
            'ore' => 9,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assegnazioni_sostegno', ['classe_id' => $classe->id, 'docente_id' => $docente->id, 'ore' => 9]);
    }

    public function test_non_accetta_due_assegnazioni_per_lo_stesso_docente_e_classe(): void
    {
        $classe = Classe::factory()->create();
        $assegnazione = AssegnazioneSostegno::factory()->create(['classe_id' => $classe->id]);

        $response = $this->actingAs($this->referente())->post("/classi/{$classe->id}/sostegno/assegnazioni", [
            'docente_id' => $assegnazione->docente_id,
            'ore' => 3,
        ]);

        $response->assertSessionHasErrors('docente_id');
    }

    public function test_aggiorna_il_conteggio_sostegno_della_classe(): void
    {
        $classe = Classe::factory()->create();

        $response = $this->actingAs($this->referente())
            ->put("/classi/{$classe->id}/sostegno/conteggio", ['conteggio_sostegno' => 'per_classe']);

        $response->assertRedirect();
        $this->assertSame('per_classe', $classe->fresh()->conteggio_sostegno);
        $this->assertSame('per_classe', $classe->fresh()->conteggioSostegnoEffettivo());
    }

    public function test_il_conteggio_non_impostato_eredita_il_default_di_istituto(): void
    {
        $classe = Classe::factory()->create(['conteggio_sostegno' => null]);

        $this->assertSame('per_alunno', $classe->conteggioSostegnoEffettivo());
    }
}
