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
}
