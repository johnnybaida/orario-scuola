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

    /** Dati minimi del form di modifica classe, più le sezioni extra. */
    private function modifica(Classe $classe, array $extra = []): array
    {
        return [
            'anno_corso' => $classe->anno_corso, 'sezione' => $classe->sezione, 'sede_id' => $classe->sede_id,
            'quadro_orario_id' => $classe->quadro_orario_id, 'tempo_scuola' => $classe->tempo_scuola,
            'n_alunni' => $classe->n_alunni, 'sezioni_extra' => 1,
        ] + $extra;
    }

    public function test_salva_fabbisogni_e_assegnazioni_dal_form_della_classe(): void
    {
        $classe = Classe::factory()->create();
        $docente = Docente::factory()->create(['tipo_posto' => 'sostegno']);

        $this->actingAs($this->referente())->put("/classi/{$classe->id}", $this->modifica($classe, [
            'conteggio_sostegno' => 'per_classe',
            'fabbisogni' => ['n0' => ['codice_anonimo' => '1B-S1', 'ore_settimanali' => 9, 'docente_unico' => '1']],
            'assegnazioni' => ['n0' => ['docente_id' => $docente->id, 'ore' => 9]],
        ]))->assertRedirect();

        $this->assertDatabaseHas('fabbisogni_sostegno', [
            'classe_id' => $classe->id, 'codice_anonimo' => '1B-S1', 'ore_settimanali' => 9, 'docente_unico' => true,
        ]);
        $this->assertDatabaseHas('assegnazioni_sostegno', ['classe_id' => $classe->id, 'docente_id' => $docente->id, 'ore' => 9]);
        $this->assertSame('per_classe', $classe->fresh()->conteggioSostegnoEffettivo());
    }

    public function test_rimuove_le_righe_assenti_e_aggiorna_quelle_con_id(): void
    {
        $classe = Classe::factory()->create();
        $tenuto = FabbisognoSostegno::factory()->create(['classe_id' => $classe->id, 'codice_anonimo' => '1B-S1', 'ore_settimanali' => 5]);
        $tolto = FabbisognoSostegno::factory()->create(['classe_id' => $classe->id, 'codice_anonimo' => '1B-S2']);

        $this->actingAs($this->referente())->put("/classi/{$classe->id}", $this->modifica($classe, [
            'fabbisogni' => [['id' => $tenuto->id, 'codice_anonimo' => '1B-S1', 'ore_settimanali' => 7]],
        ]))->assertRedirect();

        $this->assertSame(7, $tenuto->fresh()->ore_settimanali);
        $this->assertModelMissing($tolto);
    }

    public function test_non_accetta_due_fabbisogni_con_lo_stesso_codice_o_due_assegnazioni_allo_stesso_docente(): void
    {
        $classe = Classe::factory()->create();
        $docente = Docente::factory()->create(['tipo_posto' => 'sostegno']);

        $this->actingAs($this->referente())->put("/classi/{$classe->id}", $this->modifica($classe, [
            'fabbisogni' => [
                ['codice_anonimo' => '1B-S1', 'ore_settimanali' => 9],
                ['codice_anonimo' => '1B-S1', 'ore_settimanali' => 6],
            ],
            'assegnazioni' => [['docente_id' => $docente->id, 'ore' => 3], ['docente_id' => $docente->id, 'ore' => 3]],
        ]))->assertSessionHasErrors(['fabbisogni.0.codice_anonimo', 'assegnazioni.0.docente_id']);

        $this->assertDatabaseCount('fabbisogni_sostegno', 0);
    }

    public function test_le_cattedre_della_classe_si_salvano_solo_con_i_permessi_di_anagrafica(): void
    {
        $classe = Classe::factory()->create();
        $docente = Docente::factory()->create();
        $disciplina = \App\Models\Disciplina::factory()->create();
        $riga = ['docente_id' => $docente->id, 'disciplina_id' => $disciplina->id, 'ore' => 3];

        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))
            ->put("/classi/{$classe->id}", $this->modifica($classe, ['cattedre_inviate' => 1, 'cattedre' => [$riga]]))
            ->assertForbidden();

        $this->actingAs($this->referente())
            ->put("/classi/{$classe->id}", $this->modifica($classe, ['cattedre_inviate' => 1, 'cattedre' => [$riga, $riga]]))
            ->assertSessionHasErrors('cattedre');

        $this->put("/classi/{$classe->id}", $this->modifica($classe, ['cattedre_inviate' => 1, 'cattedre' => [$riga]]))->assertRedirect();
        $this->assertDatabaseHas('cattedre', ['classe_id' => $classe->id, 'docente_id' => $docente->id, 'ore' => 3]);
    }

    public function test_il_conteggio_non_impostato_eredita_il_default_di_istituto(): void
    {
        $classe = Classe::factory()->create(['conteggio_sostegno' => null]);

        $this->assertSame('per_alunno', $classe->conteggioSostegnoEffettivo());
    }

    public function test_i_docenti_di_sostegno_senza_fabbisogni_arrivano_comunque_al_generatore(): void
    {
        $classe = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'B']);
        $docente = Docente::factory()->create(['cognome' => 'Governa', 'tipo_posto' => 'sostegno']);
        $classe->assegnazioniSostegno()->create(['docente_id' => $docente->id, 'ore' => 9]);

        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10);
        $this->assertCount(1, $problema['sostegno']);
        $this->assertSame([], $problema['sostegno'][0]['fabbisogni']);
        $this->assertSame([['id' => $docente->id, 'ore' => 9]], $problema['sostegno'][0]['docenti']);
        $this->assertSame([], (new \App\Services\Validation\PreValidator)->avvisi());   // non è più un problema da segnalare
    }

    public function test_un_docente_di_sostegno_si_assegna_anche_senza_fabbisogni_e_se_ci_sono_le_ore_devono_corrispondere(): void
    {
        $classe = Classe::factory()->create();
        [$a, $b] = Docente::factory()->count(2)->create(['tipo_posto' => 'sostegno'])->all();
        $referente = $this->actingAs($this->referente());

        // senza fabbisogni: le ore dei docenti sono il bisogno
        $referente->put("/classi/{$classe->id}", $this->modifica($classe, ['assegnazioni' => [['docente_id' => $a->id, 'ore' => 9]]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assegnazioni_sostegno', ['classe_id' => $classe->id, 'docente_id' => $a->id, 'ore' => 9]);

        // con i fabbisogni le ore devono tornare (conteggio per alunno: 6 + 3 = 9 richieste, 12 assegnate)
        $fabbisogni = [['codice_anonimo' => '1B-S1', 'ore_settimanali' => 6], ['codice_anonimo' => '1B-S2', 'ore_settimanali' => 3]];
        $referente->put("/classi/{$classe->id}", $this->modifica($classe, ['fabbisogni' => $fabbisogni, 'assegnazioni' => [['docente_id' => $a->id, 'ore' => 12]]]))
            ->assertSessionHasErrors('assegnazioni');
        $this->assertStringContainsString('12h', session('errors')->first('assegnazioni'));
        $this->assertStringContainsString('9h', session('errors')->first('assegnazioni'));
        $this->assertDatabaseCount('fabbisogni_sostegno', 0);   // niente salvato a metà

        // ore che corrispondono: due docenti 6 + 3
        $referente->put("/classi/{$classe->id}", $this->modifica($classe, ['fabbisogni' => $fabbisogni, 'assegnazioni' => [['docente_id' => $a->id, 'ore' => 6], ['docente_id' => $b->id, 'ore' => 3]]]))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assegnazioni_sostegno', 2);
    }

    public function test_con_il_conteggio_per_classe_valgono_le_ore_del_fabbisogno_piu_alto(): void
    {
        $classe = Classe::factory()->create();
        $a = Docente::factory()->create(['tipo_posto' => 'sostegno']);
        $fabbisogni = [['codice_anonimo' => '1B-S1', 'ore_settimanali' => 6], ['codice_anonimo' => '1B-S2', 'ore_settimanali' => 3]];

        $this->actingAs($this->referente())->put("/classi/{$classe->id}", $this->modifica($classe, [
            'conteggio_sostegno' => 'per_classe', 'fabbisogni' => $fabbisogni, 'assegnazioni' => [['docente_id' => $a->id, 'ore' => 6]],
        ]))->assertSessionHasNoErrors();   // 6 = il più alto, non la somma (9)
    }

    public function test_con_le_assegnazioni_invariate_il_controllo_delle_ore_non_scatta(): void
    {
        $classe = Classe::factory()->create(['n_alunni' => 20]);
        $a = Docente::factory()->create(['tipo_posto' => 'sostegno']);
        $classe->assegnazioniSostegno()->create(['docente_id' => $a->id, 'ore' => 9]);
        $classe->fabbisogniSostegno()->create(['codice_anonimo' => '1B-S1', 'ore_settimanali' => 5]);   // dati vecchi: 9h assegnate, 5h richieste
        $fabbisogno = ['id' => $classe->fabbisogniSostegno()->first()->id, 'codice_anonimo' => '1B-S1', 'ore_settimanali' => 5];

        // le assegnazioni non cambiano: si può salvare altro
        $this->actingAs($this->referente())->put("/classi/{$classe->id}", array_replace($this->modifica($classe), [
            'n_alunni' => 22, 'fabbisogni' => [$fabbisogno], 'assegnazioni' => [['docente_id' => $a->id, 'ore' => 9]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(22, $classe->fresh()->n_alunni);

        // se cambiano le ore, il controllo scatta
        $this->put("/classi/{$classe->id}", array_replace($this->modifica($classe), ['fabbisogni' => [$fabbisogno], 'assegnazioni' => [['docente_id' => $a->id, 'ore' => 12]]]))->assertSessionHasErrors('assegnazioni');
    }
}
