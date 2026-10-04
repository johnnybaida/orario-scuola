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
}
