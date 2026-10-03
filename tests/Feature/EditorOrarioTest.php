<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorOrarioTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    /** Due slot nello stesso giorno, una classe che li usa entrambi. */
    private function classeConDueSlot(): array
    {
        $classe = Classe::factory()->create();
        $slot1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $slot2 = Slot::factory()->create(['giorno' => 1, 'ordine' => 2]);
        $classe->slotAttivi()->sync([$slot1->id, $slot2->id]);

        return [$classe, $slot1, $slot2];
    }

    public function test_sposta_una_lezione_in_uno_slot_libero(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame($slot2->id, $lezione->fresh()->slot_id);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'spostamento']);
    }

    public function test_scambia_due_lezioni_quando_lo_slot_e_occupato(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedraA = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id]);
        $lezioneB = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertOk();
        $this->assertSame($slot2->id, $lezioneA->fresh()->slot_id);
        $this->assertSame($slot1->id, $lezioneB->fresh()->slot_id);
    }

    public function test_rifiuta_lo_spostamento_se_il_docente_e_gia_occupato(): void
    {
        [$classeA, $slot1, $slot2] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);

        $docente = Docente::factory()->create();
        $orario = Orario::factory()->create();

        $cattedraA = Cattedra::factory()->create(['classe_id' => $classeA->id, 'docente_id' => $docente->id]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classeB->id, 'docente_id' => $docente->id]);

        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame($slot1->id, $lezioneA->fresh()->slot_id);
    }

    public function test_rifiuta_lo_spostamento_in_uno_slot_di_indisponibilita_del_docente(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $docente = Docente::factory()->create();
        $docente->indisponibilita()->attach($slot2->id);

        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $docente->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertStatus(422);
        $this->assertSame($slot1->id, $lezione->fresh()->slot_id);
    }

    public function test_una_lezione_bloccata_non_si_sposta(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create([
            'orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id, 'bloccata' => true,
        ]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertStatus(422);
    }

    public function test_rifiuta_lo_spostamento_se_la_palestra_e_piena(): void
    {
        [$classeA, $slot1, $slot2] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);

        Aula::factory()->create(['tipo' => 'palestra', 'capienza' => 1]);
        $motoria = Disciplina::factory()->create(['tipo_aula_richiesto' => 'palestra']);

        $orario = Orario::factory()->create();
        $cattedraA = Cattedra::factory()->create(['classe_id' => $classeA->id, 'disciplina_id' => $motoria->id]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classeB->id, 'disciplina_id' => $motoria->id]);

        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertStatus(422);
    }

    public function test_blocca_e_sblocca_una_lezione_con_audit_log(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->postJson("/orari/{$orario->id}/lezioni/{$lezione->id}/blocca");

        $response->assertOk()->assertJson(['bloccata' => true]);
        $this->assertTrue($lezione->fresh()->bloccata);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'blocco']);
    }

    public function test_annulla_ultima_modifica_ripristina_lo_slot_precedente(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $referente = $this->referente();
        $this->actingAs($referente)->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);
        $this->assertSame($slot2->id, $lezione->fresh()->slot_id);

        $response = $this->actingAs($referente)->post("/orari/{$orario->id}/annulla-ultima");

        $response->assertRedirect();
        $this->assertSame($slot1->id, $lezione->fresh()->slot_id);
    }

    public function test_un_docente_non_puo_spostare_lezioni(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $docente = User::factory()->create(['ruolo' => 'docente']);

        $response = $this->actingAs($docente)
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);

        $response->assertForbidden();
    }

    public function test_cambia_docente_e_materia_di_una_lezione(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 1]);
        $cattedraNuova = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 1]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraNuova->id]);

        $response->assertOk()->assertJson(['ok' => true]);
        $this->assertSame($cattedraNuova->id, $lezione->fresh()->cattedra_id);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'cambio_cattedra']);
    }

    public function test_rifiuta_il_cambio_cattedra_se_il_nuovo_docente_e_gia_occupato(): void
    {
        [$classeA, $slot1, $slot2] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);

        $docenteOccupato = Docente::factory()->create();
        $orario = Orario::factory()->create();

        $cattedraOccupata = Cattedra::factory()->create(['classe_id' => $classeB->id, 'docente_id' => $docenteOccupato->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraOccupata->id, 'slot_id' => $slot1->id]);

        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classeA->id]);
        $cattedraNuova = Cattedra::factory()->create(['classe_id' => $classeA->id, 'docente_id' => $docenteOccupato->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraNuova->id]);

        $response->assertStatus(422);
        $this->assertSame($cattedraVecchia->id, $lezione->fresh()->cattedra_id);
    }

    public function test_rifiuta_il_cambio_cattedra_verso_unaltra_classe(): void
    {
        [$classeA, $slot1] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();

        $orario = Orario::factory()->create();
        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classeA->id]);
        $cattedraAltraClasse = Cattedra::factory()->create(['classe_id' => $classeB->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraAltraClasse->id]);

        $response->assertStatus(422);
    }

    public function test_una_lezione_bloccata_non_cambia_cattedra(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $cattedraNuova = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create([
            'orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id, 'bloccata' => true,
        ]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraNuova->id]);

        $response->assertStatus(422);
    }

    public function test_il_cambio_cattedra_segnala_uno_sbilanciamento_delle_ore_senza_bloccare(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 3]);
        $cattedraNuova = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 2]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id]);

        $response = $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraNuova->id]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('avvisi'));
    }

    public function test_annulla_ultima_modifica_ripristina_anche_un_cambio_cattedra(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedraVecchia = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $cattedraNuova = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraVecchia->id, 'slot_id' => $slot1->id]);

        $referente = $this->referente();
        $this->actingAs($referente)->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedraNuova->id]);
        $this->assertSame($cattedraNuova->id, $lezione->fresh()->cattedra_id);

        $response = $this->actingAs($referente)->post("/orari/{$orario->id}/annulla-ultima");

        $response->assertRedirect();
        $this->assertSame($cattedraVecchia->id, $lezione->fresh()->cattedra_id);
    }

    public function test_un_conflitto_di_spostamento_resta_visibile_come_avviso_persistente(): void
    {
        [$classeA, $slot1, $slot2] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);

        $docente = Docente::factory()->create();
        $orario = Orario::factory()->create();

        $cattedraA = Cattedra::factory()->create(['classe_id' => $classeA->id, 'docente_id' => $docente->id]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classeB->id, 'docente_id' => $docente->id]);

        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id]);

        $this->actingAs($this->referente())
            ->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id]);

        $this->assertDatabaseHas('avvisi_orario', ['orario_id' => $orario->id, 'tipo' => 'errore']);
    }

    public function test_azzera_avvisi_svuota_il_pannello(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 5]);
        $lezione = Lezione::factory()->create([
            'orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id, 'bloccata' => true,
        ]);

        $referente = $this->referente();
        $this->actingAs($referente)->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot1->id]);
        $this->assertDatabaseCount('avvisi_orario', 1);

        $response = $this->actingAs($referente)->post("/orari/{$orario->id}/avvisi/azzera");

        $response->assertRedirect();
        $this->assertDatabaseCount('avvisi_orario', 0);
    }

    public function test_la_select_della_cattedra_e_ricercabile_e_mostra_nome_e_cognome_del_docente(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $docente = Docente::factory()->create(['nome' => 'Giulia', 'cognome' => 'Bianchi']);
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $docente->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $this->actingAs($this->referente())->get("/orari/{$orario->id}/classe/{$classe->id}")
            ->assertOk()
            ->assertSee('data-ricerca="compatta"', false)
            ->assertSee('Bianchi Giulia');
    }
}
