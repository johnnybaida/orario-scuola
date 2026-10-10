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

class SostituzioniOrarioTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    /** Un orario pubblicato con due lezioni del docente assente (lunedì 1ª e 2ª ora) e un'ora di sostegno dello stesso docente nella 3ª. */
    private function scenario(): array
    {
        $classe = Classe::factory()->create();
        $slot = collect([1, 2, 3])->map(fn ($o) => Slot::factory()->create(['giorno' => 1, 'ordine' => $o]));
        $classe->slotAttivi()->sync($slot->pluck('id'));
        [$assente, $supplente] = Docente::factory()->count(2)->create();
        $orario = Orario::factory()->create(['stato' => 'pubblicato', 'nome' => 'Orario base']);
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $assente->id]);
        $lezioni = $slot->take(2)->map(fn ($s) => Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $s->id]))->values();
        $orario->compresenzeSostegno()->create(['docente_id' => $assente->id, 'classe_id' => $classe->id, 'slot_id' => $slot[2]->id]);

        return [$orario, $assente, $supplente, $lezioni, $slot, $classe, $cattedra];
    }

    public function test_la_sostituzione_in_massa_su_una_copia_lascia_intatto_l_originale(): void
    {
        [$orario, $assente, $supplente] = $this->scenario();

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/sostituzione", [
            'assente_id' => $assente->id, 'supplente_id' => $supplente->id, 'modo' => 'copia', 'titolare' => 1, 'clil' => 1, 'sostegno' => 1,
        ])->assertRedirect();

        $copia = Orario::query()->where('id', '!=', $orario->id)->firstOrFail();
        $this->assertSame($orario->id, $copia->origine_id);
        $this->assertSame('bozza', $copia->stato);
        $this->assertSame([$supplente->id, $supplente->id], $copia->lezioni()->orderBy('slot_id')->pluck('docente_sostituto_id')->all());
        $this->assertSame([$supplente->id], $copia->compresenzeSostegno()->pluck('docente_id')->all());
        $this->assertSame([$assente->id], $copia->compresenzeSostegno()->pluck('docente_originale_id')->all());
        // l'orario di partenza non cambia
        $this->assertSame([null, null], $orario->lezioni()->orderBy('slot_id')->pluck('docente_sostituto_id')->all());
        $this->assertSame([$assente->id], $orario->compresenzeSostegno()->pluck('docente_id')->all());
        $this->assertSame('pubblicato', $orario->fresh()->stato);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Orario', 'entita_id' => $copia->id, 'azione' => 'sostituzione']);

        // il docente effettivo segue il sostituto: il supplente ha le ore, l'assente no
        $lezione = $copia->lezioni()->with('cattedra')->first();
        $this->assertSame([$supplente->id], $lezione->docentiIds());
        $this->assertSame(2, Lezione::query()->where('orario_id', $copia->id)->delDocente($supplente->id)->count());
        $this->assertSame(0, Lezione::query()->where('orario_id', $copia->id)->delDocente($assente->id)->count());
    }

    public function test_le_ore_in_cui_il_supplente_non_e_libero_restano_all_assente_e_si_segnalano(): void
    {
        [$orario, $assente, $supplente, , $slot, $classe] = $this->scenario();
        $altra = Classe::factory()->create();
        $altra->slotAttivi()->sync([$slot[0]->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'slot_id' => $slot[0]->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $altra->id, 'docente_id' => $supplente->id])->id]);

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/sostituzione", [
            'assente_id' => $assente->id, 'supplente_id' => $supplente->id, 'modo' => 'copia', 'titolare' => 1, 'sostegno' => 1,
        ])->assertRedirect()->assertSessionHas('avviso');

        $copia = Orario::query()->where('origine_id', $orario->id)->firstOrFail();
        $this->assertSame([null, $supplente->id], $copia->lezioni()->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))->orderBy('slot_id')->pluck('docente_sostituto_id')->all());
        $this->assertDatabaseHas('avvisi_orario', ['orario_id' => $copia->id, 'tipo' => 'avviso']);
        $this->assertStringContainsString('Sostituzione non applicata', $copia->avvisi()->first()->messaggio);
    }

    public function test_si_sostituiscono_solo_i_giorni_scelti_e_si_puo_applicare_alla_bozza(): void
    {
        [$orario, $assente, $supplente] = $this->scenario();
        $orario->update(['stato' => 'bozza']);
        $martedi = Slot::factory()->create(['giorno' => 2, 'ordine' => 1]);
        $prima = $orario->lezioni()->first();
        $prima->cattedra->classe->slotAttivi()->attach($martedi->id);
        Lezione::factory()->create(['orario_id' => $orario->id, 'slot_id' => $martedi->id, 'cattedra_id' => $prima->cattedra_id]);

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/sostituzione", [
            'assente_id' => $assente->id, 'supplente_id' => $supplente->id, 'modo' => 'bozza', 'titolare' => 1, 'giorni' => [2],
        ])->assertRedirect();

        $this->assertSame(1, Lezione::query()->where('orario_id', $orario->id)->where('docente_sostituto_id', $supplente->id)->count());   // solo il martedì
        $this->assertSame(1, Orario::query()->count());   // nessuna copia
    }

    public function test_il_pannello_imposta_il_sostituto_di_un_ora_con_i_controlli_e_si_annulla(): void
    {
        [$orario, $assente, $supplente, $lezioni, $slot] = $this->scenario();
        $orario->update(['stato' => 'bozza']);
        $referente = $this->actingAs($this->referente());
        $url = "/orari/{$orario->id}/lezioni/{$lezioni[0]->id}/sostituto";

        $referente->getJson("/orari/{$orario->id}/lezioni/{$lezioni[0]->id}/dettaglio")->assertOk()
            ->assertJsonPath('sostituzione.titolare.originale', $assente->nomeCompleto())
            ->assertJsonFragment(['id' => $supplente->id, 'libero' => true]);

        $referente->patchJson($url, ['ruolo' => 'titolare', 'docente_id' => $supplente->id])->assertOk();
        $this->assertSame($supplente->id, $lezioni[0]->fresh()->docente_sostituto_id);

        $referente->post("/orari/{$orario->id}/annulla-ultima");
        $this->assertNull($lezioni[0]->fresh()->docente_sostituto_id);
        $referente->post("/orari/{$orario->id}/ripeti");
        $this->assertSame($supplente->id, $lezioni[0]->fresh()->docente_sostituto_id);

        // scegliere il titolare originale toglie la sostituzione
        $referente->patchJson($url, ['ruolo' => 'titolare', 'docente_id' => $assente->id])->assertOk();
        $this->assertNull($lezioni[0]->fresh()->docente_sostituto_id);

        // supplente occupato nello stesso slot: rifiutato, accettato in provvisorio
        $altra = Classe::factory()->create();
        $altra->slotAttivi()->sync([$slot[0]->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'slot_id' => $slot[0]->id, 'cattedra_id' => Cattedra::factory()->create(['classe_id' => $altra->id, 'docente_id' => $supplente->id])->id]);
        $referente->patchJson($url, ['ruolo' => 'titolare', 'docente_id' => $supplente->id])->assertStatus(422);
        $this->assertNull($lezioni[0]->fresh()->docente_sostituto_id);
        $referente->patchJson($url, ['ruolo' => 'titolare', 'docente_id' => $supplente->id, 'provvisorio' => true])->assertOk();
        $this->assertTrue(collect((new \App\Services\Editor\ControlloOrario)->problemi($orario))->contains(fn ($p) => $p['gravita'] === 'errore' && str_contains($p['testo'], 'è in due posti')));

        // cambiare cattedra azzera il sostituto
        $altraCattedra = Cattedra::factory()->create(['classe_id' => $lezioni[0]->cattedra->classe_id]);
        $referente->patchJson("/orari/{$orario->id}/lezioni/{$lezioni[0]->id}/cattedra", ['cattedra_id' => $altraCattedra->id, 'provvisorio' => true])->assertOk();
        $this->assertNull($lezioni[0]->fresh()->docente_sostituto_id);
    }

    public function test_rientro_ripubblica_l_originale_e_puo_eliminare_la_copia(): void
    {
        [$orario, $assente, $supplente] = $this->scenario();
        $amministratore = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $amministratore->post("/orari/{$orario->id}/sostituzione", ['assente_id' => $assente->id, 'supplente_id' => $supplente->id, 'modo' => 'copia', 'titolare' => 1]);
        $copia = Orario::query()->where('origine_id', $orario->id)->firstOrFail();
        // la copia va in pubblicazione (l'originale si archivia)
        $copia->update(['stato' => 'approvato']);
        $amministratore->post("/orari/{$copia->id}/stato", ['stato' => 'pubblicato']);
        $this->assertSame('archiviato', $orario->fresh()->stato);

        // il referente non può fare il rientro; l'amministratore sì
        $this->actingAs($this->referente())->post("/orari/{$copia->id}/rientro")->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']))->post("/orari/{$copia->id}/rientro", ['elimina_copia' => 1])->assertRedirect();

        $this->assertSame('pubblicato', $orario->fresh()->stato);
        $this->assertModelMissing($copia);
    }

    public function test_un_orario_archiviato_si_puo_ripubblicare_solo_da_chi_approva(): void
    {
        [$orario] = $this->scenario();
        $orario->update(['stato' => 'archiviato']);

        $this->actingAs($this->referente())->post("/orari/{$orario->id}/stato", ['stato' => 'pubblicato'])->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']))->post("/orari/{$orario->id}/stato", ['stato' => 'pubblicato'])->assertRedirect();
        $this->assertSame('pubblicato', $orario->fresh()->stato);
    }

    public function test_i_pdf_si_generano_con_le_sostituzioni(): void
    {
        [$orario, $assente, $supplente] = $this->scenario();
        $this->actingAs($this->referente())->post("/orari/{$orario->id}/sostituzione", ['assente_id' => $assente->id, 'supplente_id' => $supplente->id, 'modo' => 'copia', 'titolare' => 1, 'sostegno' => 1]);
        $copia = Orario::query()->where('origine_id', $orario->id)->firstOrFail();
        $esporta = app(\App\Services\Export\OrarioPdfExporter::class);

        foreach ([$esporta->classi($copia), $esporta->docenti($copia), $esporta->aule($copia), $esporta->generale($copia, 'classe'), $esporta->generale($copia, 'aula')] as $pdf) {
            $this->assertStringStartsWith('%PDF', $pdf->output());
        }
    }
}
