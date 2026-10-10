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

    public function test_gli_avvisi_dicono_classe_giorno_ora_e_l_altra_lezione_in_conflitto(): void
    {
        $classeA = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A']);
        $classeB = Classe::factory()->create(['anno_corso' => 2, 'sezione' => 'B']);
        $slot1 = Slot::factory()->create(['giorno' => 2, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
        $slot2 = Slot::factory()->create(['giorno' => 2, 'ordine' => 3, 'inizio' => '09:40:00', 'fine' => '10:30:00']);
        $classeA->slotAttivi()->sync([$slot1->id, $slot2->id]);
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);
        $docente = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $orario = Orario::factory()->create();
        $cattedraA = Cattedra::factory()->create(['classe_id' => $classeA->id, 'docente_id' => $docente->id]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classeB->id, 'docente_id' => $docente->id]);
        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id]);

        $this->actingAs($this->referente())->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id]);

        $messaggio = \App\Models\AvvisoOrario::query()->where('orario_id', $orario->id)->value('messaggio');
        foreach ([$classeA->nomeCompleto(), 'martedì, 3ª ora (09:40–10:30)', 'Rossi Anna', $classeB->nomeCompleto()] as $atteso) {
            $this->assertStringContainsString($atteso, $messaggio);
        }

        // il pannello della classe mostra il messaggio completo
        $this->actingAs($this->referente())->get("/orari/{$orario->id}/classe/{$classeA->id}")->assertSee('martedì, 3ª ora (09:40–10:30)', false);
    }

    public function test_il_cambio_di_cattedra_distingue_nell_avviso_la_cattedra_lasciata_da_quella_scelta(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $vecchioDocente = Docente::factory()->create(['cognome' => 'Lasciato', 'nome' => 'Luca']);
        $nuovoDocente = Docente::factory()->create(['cognome' => 'Scelto', 'nome' => 'Sara']);
        $vecchia = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $vecchioDocente->id, 'ore' => 3]);
        $nuova = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $nuovoDocente->id, 'ore' => 3]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $vecchia->id, 'slot_id' => $slot1->id]);

        $this->actingAs($this->referente())->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $nuova->id])->assertOk();
        $this->assertSame($nuova->id, $lezione->fresh()->cattedra_id); // la scelta è quella fatta

        $messaggi = \App\Models\AvvisoOrario::query()->where('orario_id', $orario->id)->pluck('messaggio');
        $lasciata = $messaggi->first(fn ($m) => str_contains($m, 'cattedra lasciata'));
        $scelta = $messaggi->first(fn ($m) => str_contains($m, 'cattedra scelta'));
        $this->assertStringContainsString('Lasciato Luca', $lasciata);
        $this->assertStringContainsString('Scelto Sara', $scelta);
        $this->assertStringContainsString($classe->nomeCompleto(), $lasciata);
        $this->assertStringContainsString('lunedì, 1ª ora', $lasciata);
    }

    public function test_una_modifica_rifiutata_segna_in_errore_il_riquadro_e_la_select_resta_sulla_cattedra_reale(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $italiano = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $arte = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $storia = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $arte->docente->indisponibilita()->attach($slot1->id);   // Arte non può essere in quello slot
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $italiano->id, 'slot_id' => $slot1->id]);
        $altra = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $storia->id, 'slot_id' => $slot2->id]);
        $referente = $this->actingAs($this->referente());

        $referente->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $arte->id])->assertStatus(422);
        $this->assertSame($italiano->id, $lezione->fresh()->cattedra_id); // niente è cambiato
        $this->assertDatabaseHas('avvisi_orario', ['orario_id' => $orario->id, 'lezione_id' => $lezione->id, 'tipo' => 'errore']);

        $html = $referente->get("/orari/{$orario->id}/classe/{$classe->id}")->getContent();
        $this->assertSame(1, substr_count($html, 'aria-invalid="true"'));                  // solo il riquadro interessato
        $this->assertSame(1, substr_count($html, 'Modifica rifiutata'));
        // la select di quella lezione ha selezionata la cattedra reale (Italiano), non Arte né Storia
        preg_match('/data-lezione-id="'.$lezione->id.'" data-attuale="(\d+)".*?<\/select>/s', $html, $m);
        $this->assertSame((string) $italiano->id, $m[1]);
        $this->assertMatchesRegularExpression('/<option value="'.$italiano->id.'" selected>/', $m[0]);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$arte->id.'" selected>/', $m[0]);

        $referente->post("/orari/{$orario->id}/avvisi/azzera");
        $this->assertStringNotContainsString('aria-invalid', $referente->get("/orari/{$orario->id}/classe/{$classe->id}")->getContent());
    }

    public function test_azzera_avvisi_svuota_il_pannello(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'ore' => 5]);
        $lezione = Lezione::factory()->create([
            'orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id, 'bloccata' => true,
        ]);

        $referente = $this->referente();
        $this->actingAs($referente)->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);   // lezione bloccata: rifiutato
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

    public function test_le_cattedre_nella_select_sono_in_ordine_alfabetico_di_materia(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $scienze = Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => Disciplina::factory()->create(['nome' => 'Scienze'])->id]);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => Disciplina::factory()->create(['nome' => 'Arte'])->id]);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => Disciplina::factory()->create(['nome' => 'Italiano'])->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $scienze->id, 'slot_id' => $slot1->id]);

        $this->actingAs($this->referente())->get("/orari/{$orario->id}/classe/{$classe->id}")
            ->assertOk()->assertSeeInOrder(['Arte - ', 'Italiano - ', 'Scienze - ']);
    }

    private function orarioConLezione(string $stato = 'bozza'): array
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create(['stato' => $stato, 'versione' => 1]);
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id, 'bloccata' => true]);

        return [$orario, $lezione, $classe, $slot1, $slot2];
    }

    public function test_duplica_un_orario_come_nuova_bozza_con_lezioni_e_sostegno_ma_senza_avvisi(): void
    {
        [$orario, $lezione, $classe, $slot1] = $this->orarioConLezione('approvato');
        $sostegno = Docente::factory()->create();
        \App\Models\CompresenzaSostegno::query()->create(['orario_id' => $orario->id, 'docente_id' => $sostegno->id, 'classe_id' => $classe->id, 'slot_id' => $slot1->id]);
        \App\Models\AvvisoOrario::query()->create(['orario_id' => $orario->id, 'tipo' => 'avviso', 'messaggio' => 'prova']);

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/duplica")->assertRedirect(route('orari.index'))->assertSessionHas('successo');

        $copia = Orario::query()->where('id', '!=', $orario->id)->firstOrFail();
        $this->assertSame('bozza', $copia->stato);
        $this->assertSame(2, $copia->versione);
        $this->assertSame($orario->periodo_id, $copia->periodo_id);
        $this->assertDatabaseHas('lezioni', ['orario_id' => $copia->id, 'cattedra_id' => $lezione->cattedra_id, 'slot_id' => $slot1->id, 'bloccata' => true]);
        $this->assertDatabaseHas('compresenze_sostegno', ['orario_id' => $copia->id, 'docente_id' => $sostegno->id]);
        $this->assertDatabaseMissing('avvisi_orario', ['orario_id' => $copia->id]);
        $this->assertSame('approvato', $orario->fresh()->stato); // l'originale non cambia
        $this->assertDatabaseHas('audit_log', ['entita' => 'Orario', 'entita_id' => $copia->id, 'azione' => 'duplicazione']);

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->post("/orari/{$orario->id}/duplica")->assertForbidden();
    }

    public function test_il_ciclo_di_stato_rispetta_i_permessi_e_pubblicare_archivia_il_precedente(): void
    {
        [$orario] = $this->orarioConLezione();
        $vecchio = Orario::factory()->create(['periodo_id' => $orario->periodo_id, 'stato' => 'pubblicato', 'versione' => 0]);
        $referente = $this->referente();
        $dirigente = User::factory()->create(['ruolo' => 'ds']);

        $this->actingAs($referente)->post("/orari/{$orario->id}/stato", ['stato' => 'in_revisione'])->assertRedirect();
        $this->assertSame('in_revisione', $orario->fresh()->stato);

        // il referente non approva; il dirigente sì
        $this->actingAs($referente)->post("/orari/{$orario->id}/stato", ['stato' => 'approvato'])->assertForbidden();
        $this->actingAs($dirigente)->post("/orari/{$orario->id}/stato", ['stato' => 'approvato'])->assertRedirect();
        // un passaggio fuori dal ciclo è rifiutato
        $this->actingAs($dirigente)->post("/orari/{$orario->id}/stato", ['stato' => 'archiviato'])->assertStatus(422);
        // il referente non può riaprire un orario approvato
        $this->actingAs($referente)->post("/orari/{$orario->id}/stato", ['stato' => 'bozza'])->assertForbidden();

        $this->actingAs($dirigente)->post("/orari/{$orario->id}/stato", ['stato' => 'pubblicato'])->assertRedirect();
        $this->assertSame('pubblicato', $orario->fresh()->stato);
        $this->assertSame('archiviato', $vecchio->fresh()->stato); // una sola pubblicata per periodo
        $this->assertDatabaseHas('audit_log', ['entita' => 'Orario', 'entita_id' => $orario->id, 'azione' => 'cambio_stato']);

        // la segreteria non cambia stati
        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))->post("/orari/{$orario->id}/stato", ['stato' => 'archiviato'])->assertForbidden();
    }

    public function test_solo_la_bozza_si_modifica_e_si_elimina_solo_se_bozza_o_archiviato(): void
    {
        [$orario, $lezione, $classe, $slot1, $slot2] = $this->orarioConLezione('approvato');
        $lezione->update(['bloccata' => false]);
        $utente = $this->actingAs($this->referente());

        $utente->patch("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id])->assertStatus(422);
        $this->assertSame($slot1->id, $lezione->fresh()->slot_id);
        $utente->post("/orari/{$orario->id}/lezioni/{$lezione->id}/blocca")->assertStatus(422);
        $utente->post("/orari/{$orario->id}/annulla-ultima")->assertStatus(422);

        $utente->get("/orari/{$orario->id}/classe/{$classe->id}")->assertOk()
            ->assertSee('data-editabile="0"', false)->assertSee('si può solo consultare');

        $utente->delete("/orari/{$orario->id}")->assertStatus(422);
        $this->assertModelExists($orario);

        $orario->update(['stato' => 'archiviato']);
        $utente->delete("/orari/{$orario->id}")->assertRedirect();
        $this->assertModelMissing($orario);
    }

    public function test_lelenco_orari_mostra_stato_e_solo_i_pulsanti_permessi(): void
    {
        [$orario] = $this->orarioConLezione('in_revisione');

        $duplica = route('orari.duplica', $orario);

        $this->actingAs($this->referente())->get('/orari')->assertOk()->assertSee('In revisione')->assertSee($duplica, false)
            ->assertSee('Riporta in bozza')->assertDontSee('>Approva<', false);
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/orari')->assertOk()->assertSee('>Approva<', false)->assertDontSee($duplica, false);
    }

    public function test_annulla_e_ripeti_su_piu_livelli_comprese_le_modifiche_di_blocco(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $slot3 = Slot::factory()->create(['giorno' => 1, 'ordine' => 3]);
        $classe->slotAttivi()->attach($slot3->id);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);
        $utente = $this->actingAs($this->referente());
        $base = "/orari/{$orario->id}";

        // tre modifiche: sposta in slot2, sposta in slot3, blocca
        $utente->patchJson("$base/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id])->assertOk();
        $utente->patchJson("$base/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot3->id])->assertOk();
        $utente->postJson("$base/lezioni/{$lezione->id}/blocca")->assertOk();
        $this->assertTrue($lezione->fresh()->bloccata);

        // annulla ×3: sblocca, torna in slot2, torna in slot1
        $utente->post("$base/annulla-ultima")->assertSessionHas('successo');
        $this->assertFalse($lezione->fresh()->bloccata);
        $utente->post("$base/annulla-ultima");
        $this->assertSame($slot2->id, $lezione->fresh()->slot_id);
        $utente->post("$base/annulla-ultima");
        $this->assertSame($slot1->id, $lezione->fresh()->slot_id);
        $utente->post("$base/annulla-ultima")->assertSessionHasErrors('annulla'); // niente più da annullare

        // ripeti ×2: slot2, slot3
        $utente->post("$base/ripeti")->assertSessionHas('successo');
        $this->assertSame($slot2->id, $lezione->fresh()->slot_id);
        $utente->post("$base/ripeti");
        $this->assertSame($slot3->id, $lezione->fresh()->slot_id);

        // una nuova modifica azzera lo stack "ripeti" (il blocco annullato non si può più ripetere)
        $utente->patchJson("$base/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot1->id])->assertOk();
        $utente->post("$base/ripeti")->assertSessionHasErrors('annulla');
        $this->assertFalse($lezione->fresh()->bloccata);

        // il registro delle modifiche non perde nulla: annullamenti e ripristini sono registrati
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'annullamento']);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'ripristino']);
        $this->assertSame(4, \DB::table('audit_log')->where('entita_id', $lezione->id)->whereIn('azione', ['spostamento', 'blocco'])->count());
    }

    public function test_annulla_e_ripeti_ripristinano_anche_uno_scambio_di_due_lezioni(): void
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $a = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $classe->id])->id, 'slot_id' => $slot1->id]);
        $b = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $classe->id])->id, 'slot_id' => $slot2->id]);
        $utente = $this->actingAs($this->referente());

        $utente->patchJson("/orari/{$orario->id}/lezioni/{$a->id}/sposta", ['slot_id' => $slot2->id])->assertOk();
        $this->assertSame([$slot2->id, $slot1->id], [$a->fresh()->slot_id, $b->fresh()->slot_id]);

        $utente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertSame([$slot1->id, $slot2->id], [$a->fresh()->slot_id, $b->fresh()->slot_id]);
        $utente->post("/orari/{$orario->id}/ripeti");
        $this->assertSame([$slot2->id, $slot1->id], [$a->fresh()->slot_id, $b->fresh()->slot_id]);
    }

    public function test_annulla_e_ripeti_non_valgono_fuori_dalla_bozza_ne_per_i_ruoli_senza_permesso(): void
    {
        [$orario] = $this->orarioConLezione('approvato');

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/ripeti")->assertStatus(422);
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->post("/orari/{$orario->id}/ripeti")->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->post("/orari/{$orario->id}/annulla-ultima")->assertForbidden();
    }

    public function test_la_griglia_mostra_annulla_e_ripeti_attivi_solo_se_ce_qualcosa_da_fare(): void
    {
        [$orario, $lezione, $classe, $slot1, $slot2] = $this->orarioConLezione();
        $lezione->update(['bloccata' => false]);
        $utente = $this->actingAs($this->referente());

        $stato = function () use ($utente, $orario, $classe) {
            $html = $utente->get("/orari/{$orario->id}/classe/{$classe->id}")->assertOk()->getContent();
            preg_match('/<form id="form-annulla".*?<\/form>/s', $html, $annulla);
            preg_match('/<form id="form-ripeti".*?<\/form>/s', $html, $ripeti);

            // l'attributo disabled, non le classi Tailwind "disabled:..."
            return [(bool) preg_match('/\sdisabled[\s>]/', $annulla[0]), (bool) preg_match('/\sdisabled[\s>]/', $ripeti[0])];
        };

        $this->assertSame([true, true], $stato()); // niente da annullare né da ripetere

        $utente->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sposta", ['slot_id' => $slot2->id]);
        $this->assertSame([false, true], $stato()); // si può annullare

        $utente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertSame([true, false], $stato()); // ora si può ripetere
    }

    public function test_un_orario_si_nomina_si_rinomina_e_la_copia_propone_il_suo_nome(): void
    {
        $orario = Orario::factory()->create(['nome' => 'Orario di base', 'versione' => 3]);
        $referente = $this->actingAs($this->referente());

        $referente->get("/orari/{$orario->id}/duplica")->assertOk()->assertSee('Orario di base (copia)');
        $referente->post("/orari/{$orario->id}/duplica", ['nome' => 'Settimana uscita didattica'])->assertRedirect(route('orari.index'));
        $this->assertDatabaseHas('orari', ['nome' => 'Settimana uscita didattica', 'stato' => 'bozza']);

        $referente->post("/orari/{$orario->id}/duplica", ['nome' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orari', ['nome' => 'Orario di base (copia)']);

        $referente->put("/orari/{$orario->id}", ['nome' => 'Orario definitivo'])->assertRedirect(route('orari.index'));
        $this->assertSame('Orario definitivo', $orario->fresh()->nome);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Orario', 'entita_id' => $orario->id, 'azione' => 'modifica']);

        $referente->put("/orari/{$orario->id}", ['nome' => ''])->assertSessionHasNoErrors();
        $this->assertSame('Orario v3', $orario->fresh()->etichetta()); // senza nome: «Orario vN»
        $referente->get('/orari')->assertOk()->assertSee('Orario v3')->assertSee('Settimana uscita didattica');

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->put("/orari/{$orario->id}", ['nome' => 'x'])->assertForbidden();
    }

    public function test_scegliere_la_stessa_cattedra_non_e_un_errore_ne_una_modifica(): void
    {
        [$classe, $slot1] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);

        $this->actingAs($this->referente())->patchJson("/orari/{$orario->id}/lezioni/{$lezione->id}/cattedra", ['cattedra_id' => $cattedra->id])
            ->assertOk()->assertJsonPath('errori', []);
        $this->assertDatabaseCount('avvisi_orario', 0);
        $this->assertDatabaseCount('modifiche_orario', 0);
    }

    public function test_rifiuta_lo_spostamento_se_la_docente_clil_e_gia_in_un_altra_classe(): void
    {
        [$classeA, $slot1, $slot2] = $this->classeConDueSlot();
        $classeB = Classe::factory()->create();
        $classeB->slotAttivi()->sync([$slot1->id, $slot2->id]);
        $clil = Docente::factory()->create();
        $orario = Orario::factory()->create();
        $cattedraA = Cattedra::factory()->create(['classe_id' => $classeA->id, 'docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $cattedraB = Cattedra::factory()->create(['classe_id' => $classeB->id, 'docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $lezioneA = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraA->id, 'slot_id' => $slot1->id, 'con_clil' => true]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedraB->id, 'slot_id' => $slot2->id, 'con_clil' => true]);

        $this->actingAs($this->referente())->patchJson("/orari/{$orario->id}/lezioni/{$lezioneA->id}/sposta", ['slot_id' => $slot2->id])
            ->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertSame($slot1->id, $lezioneA->fresh()->slot_id);
    }

    /** Un'ora con una lezione e due docenti di sostegno, più un docente CLIL sulla cattedra. */
    private function oraConSostegno(): array
    {
        [$classe, $slot1, $slot2] = $this->classeConDueSlot();
        $orario = Orario::factory()->create();
        $clil = Docente::factory()->create(['cognome' => 'Clili']);
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $lezione = Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);
        [$s1, $s2, $s3] = Docente::factory()->count(3)->create(['tipo_posto' => 'sostegno'])->all();

        return [$orario, $lezione, $classe, $slot1, $slot2, $clil, $s1, $s2, $s3];
    }

    public function test_il_pannello_imposta_i_docenti_di_sostegno_e_si_annulla(): void
    {
        [$orario, $lezione, $classe, $slot1, , , $s1, $s2] = $this->oraConSostegno();
        $referente = $this->actingAs($this->referente());

        $referente->putJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sostegno", ['docenti' => [$s1->id, $s2->id]])->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseCount('compresenze_sostegno', 2);
        $this->assertDatabaseHas('compresenze_sostegno', ['orario_id' => $orario->id, 'classe_id' => $classe->id, 'slot_id' => $slot1->id, 'docente_id' => $s2->id]);

        // il pannello mostra i due docenti già presenti
        $referente->getJson("/orari/{$orario->id}/lezioni/{$lezione->id}/dettaglio")->assertOk()->assertJsonPath('sostegno', [$s1->id, $s2->id]);

        // se ne toglie uno
        $referente->putJson("/orari/{$orario->id}/lezioni/{$lezione->id}/sostegno", ['docenti' => [$s1->id]])->assertOk();
        $this->assertDatabaseCount('compresenze_sostegno', 1);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'cambio_sostegno']);

        // annulla riporta il docente tolto, annulla ancora li toglie entrambi; ripeti li rimette
        $referente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertDatabaseCount('compresenze_sostegno', 2);
        $referente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertDatabaseCount('compresenze_sostegno', 0);
        $referente->post("/orari/{$orario->id}/ripeti");
        $this->assertDatabaseCount('compresenze_sostegno', 2);
    }

    public function test_il_sostegno_rifiuta_un_docente_occupato_o_non_di_sostegno_salvo_provvisorio(): void
    {
        [$orario, $lezione, , $slot1, , , $s1] = $this->oraConSostegno();
        $altra = Classe::factory()->create();
        $altra->slotAttivi()->sync([$slot1->id]);
        $referente = $this->actingAs($this->referente());
        $url = "/orari/{$orario->id}/lezioni/{$lezione->id}/sostegno";

        // s1 è già in lezione nello stesso slot in un'altra classe
        $occupante = Lezione::factory()->create(['orario_id' => $orario->id, 'slot_id' => $slot1->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $altra->id, 'docente_id' => $s1->id])->id]);
        $referente->putJson($url, ['docenti' => [$s1->id]])->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertDatabaseCount('compresenze_sostegno', 0);
        $this->assertDatabaseHas('avvisi_orario', ['orario_id' => $orario->id, 'tipo' => 'errore']);

        // con i conflitti provvisori si accetta, e il Controllo lo segnala
        $referente->putJson($url, ['docenti' => [$s1->id], 'provvisorio' => true])->assertOk();
        $this->assertDatabaseCount('compresenze_sostegno', 1);
        $this->assertTrue(collect((new \App\Services\Editor\ControlloOrario)->problemi($orario))->contains(fn ($p) => $p['gravita'] === 'errore' && str_contains($p['testo'], 'è in due posti') && str_contains($p['testo'], 'sostegno')));

        // un docente che non è di sostegno non si accetta nemmeno in provvisorio
        $comune = Docente::factory()->create(['tipo_posto' => 'comune']);
        $referente->putJson($url, ['docenti' => [$s1->id, $comune->id], 'provvisorio' => true])->assertStatus(422);
        $this->assertDatabaseCount('compresenze_sostegno', 1);
    }

    public function test_il_clil_si_attiva_e_si_toglie_dal_pannello_solo_con_un_docente_clil_sulla_cattedra(): void
    {
        [$orario, $lezione, $classe, , $slot2] = $this->oraConSostegno();
        $referente = $this->actingAs($this->referente());
        $url = "/orari/{$orario->id}/lezioni/{$lezione->id}/clil";

        $referente->patchJson($url, ['attivo' => true])->assertOk();
        $this->assertTrue($lezione->fresh()->con_clil);
        $referente->getJson("/orari/{$orario->id}/lezioni/{$lezione->id}/dettaglio")->assertJsonPath('clil.attivo', true)->assertJsonPath('clil.docente', fn ($n) => str_contains($n, 'Clili'));

        $referente->patchJson($url, ['attivo' => false])->assertOk();
        $this->assertFalse($lezione->fresh()->con_clil);
        $referente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertTrue($lezione->fresh()->con_clil);

        // senza docente CLIL sulla cattedra: errore
        $senza = Lezione::factory()->create(['orario_id' => $orario->id, 'slot_id' => $slot2->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_clil_id' => null, 'ore_clil' => 0])->id]);
        $referente->patchJson("/orari/{$orario->id}/lezioni/{$senza->id}/clil", ['attivo' => true])->assertStatus(422);
    }

    public function test_il_pannello_si_apre_solo_in_bozza_e_il_pulsante_compare_nelle_viste_modificabili(): void
    {
        [$orario, $lezione, $classe] = $this->oraConSostegno();
        $referente = $this->actingAs($this->referente());

        $referente->get("/orari/{$orario->id}/classe/{$classe->id}")->assertOk()->assertSee('js-modifica-lezione', false);
        $referente->get("/orari/{$orario->id}/tabellone")->assertOk()->assertSee('js-modifica-lezione', false);

        $orario->update(['stato' => 'approvato']);
        $referente->getJson("/orari/{$orario->id}/lezioni/{$lezione->id}/dettaglio")->assertStatus(422);
        $referente->get("/orari/{$orario->id}/tabellone")->assertOk()->assertDontSee('js-modifica-lezione', false);
    }

    public function test_il_tabellone_web_mostra_il_docente_clil_nelle_ore_in_compresenza(): void
    {
        [$orario, $lezione, , , , $clil] = $this->oraConSostegno();
        $referente = $this->actingAs($this->referente());

        $referente->get("/orari/{$orario->id}/tabellone")->assertOk()->assertDontSee('Docente CLIL in compresenza');
        $lezione->update(['con_clil' => true]);
        $referente->get("/orari/{$orario->id}/tabellone")->assertOk()->assertSee('Docente CLIL in compresenza: '.$clil->nomeCompleto(), false);
    }
}
