<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnagraficheTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_crea_una_sede(): void
    {
        $response = $this->actingAs($this->referente())->post('/sedi', [
            'nome' => 'Plesso Centrale',
            'indirizzo' => 'Via Roma 1',
        ]);

        $response->assertRedirect(route('sedi.index'));
        $this->assertDatabaseHas('sedi', ['nome' => 'Plesso Centrale']);
    }

    public function test_crea_unaula_legata_a_una_sede(): void
    {
        $sede = Sede::factory()->create();

        $response = $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id,
            'nome' => 'Palestra',
            'tipo' => 'palestra',
            'capienza' => 2,
        ]);

        $response->assertRedirect(route('aule.index'));
        $this->assertDatabaseHas('aule', ['nome' => 'Palestra', 'sede_id' => $sede->id]);
    }

    public function test_aula_dada_scelta_dalla_select_collega_la_disciplina_e_la_modale_non_ha_il_layout(): void
    {
        $sede = Sede::factory()->create();
        $disciplina = Disciplina::factory()->create(['codice' => 'ITA']);

        $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id, 'nome' => 'Aula Italiano', 'tipo' => "dada:{$disciplina->id}", 'capienza' => 1,
        ])->assertRedirect(route('aule.index'));

        $this->assertDatabaseHas('aule', ['tipo' => 'dada_ita']);
        $this->assertSame('dada_ita', $disciplina->fresh()->tipo_aula_richiesto);

        $this->actingAs($this->referente())->get('/aule/create', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertDontSee('<html', false)->assertSee('DADA · '.$disciplina->nome);
    }

    public function test_crea_unaula_dada_con_tipo_libero_e_la_collega_a_una_disciplina(): void
    {
        $sede = Sede::factory()->create();

        $rispostaAula = $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id,
            'nome' => 'Aula Italiano',
            'tipo' => 'dada_italiano',
            'capienza' => 1,
        ]);
        $rispostaAula->assertRedirect(route('aule.index'));
        $this->assertDatabaseHas('aule', ['tipo' => 'dada_italiano']);

        $disciplina = Disciplina::factory()->create();
        $rispostaDisciplina = $this->actingAs($this->referente())
            ->put("/discipline/{$disciplina->id}", [
                'codice' => $disciplina->codice,
                'nome' => $disciplina->nome,
                'tipo_aula_richiesto' => 'dada_italiano',
            ]);

        $rispostaDisciplina->assertRedirect(route('discipline.index'));
        $this->assertDatabaseHas('discipline', ['id' => $disciplina->id, 'tipo_aula_richiesto' => 'dada_italiano']);
    }

    public function test_un_quadro_orario_puo_ricevere_righe_disciplina(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())
            ->post("/quadri-orari/{$quadro->id}/righe", [
                'disciplina_id' => $disciplina->id,
                'ore_settimanali' => 6,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quadro_orario_righe', [
            'quadro_orario_id' => $quadro->id,
            'disciplina_id' => $disciplina->id,
            'ore_settimanali' => 6,
        ]);
        $this->assertSame(6, $quadro->fresh()->ore_totali);
    }

    public function test_crea_un_docente_con_classi_di_concorso_e_sedi(): void
    {
        $sede = Sede::factory()->create();

        $response = $this->actingAs($this->referente())->post('/docenti', [
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'mario.rossi@scuola.test',
            'tipo_contratto' => 'tempo_indeterminato',
            'tipo_posto' => 'comune',
            'regime' => 'tempo_pieno',
            'ore_dovute' => 18,
            'classi_concorso' => ['A022', 'A028'],
            'sedi' => [$sede->id],
        ]);

        $response->assertRedirect();
        $docente = Docente::query()->where('email', 'mario.rossi@scuola.test')->firstOrFail();
        $this->assertCount(2, $docente->classiConcorso);
        $this->assertTrue($docente->sedi->contains($sede));
    }

    public function test_importa_docenti_da_csv(): void
    {
        $csv = "nome,cognome,email\nAnna,Bianchi,anna.bianchi@scuola.test\nLuca,Verdi,luca.verdi@scuola.test\n";
        $file = UploadedFile::fake()->createWithContent('docenti.csv', $csv);

        $response = $this->actingAs($this->referente())->post('/docenti-import', ['file' => $file]);

        $response->assertRedirect(route('docenti.index'));
        $this->assertDatabaseCount('docenti', 2);
        $this->assertDatabaseHas('docenti', ['email' => 'anna.bianchi@scuola.test']);
    }

    public function test_crea_una_classe_con_slot_mattutini_di_default(): void
    {
        $sede = Sede::factory()->create();
        $quadro = QuadroOrario::factory()->create();
        $this->seedSlotMattutini();

        $response = $this->actingAs($this->referente())->post('/classi', [
            'anno_corso' => 1,
            'sezione' => 'A',
            'sede_id' => $sede->id,
            'quadro_orario_id' => $quadro->id,
            'tempo_scuola' => 'normale',
            'n_alunni' => 20,
        ]);

        $response->assertRedirect();
        $classe = Classe::query()->where('sezione', 'A')->firstOrFail();
        $this->assertCount(30, $classe->slotAttivi);
    }

    public function test_crea_una_cattedra(): void
    {
        $docente = Docente::factory()->create();
        $classe = Classe::factory()->create();
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/cattedre', [
            'docente_id' => $docente->id,
            'classe_id' => $classe->id,
            'disciplina_id' => $disciplina->id,
            'ore' => 6,
        ]);

        $response->assertRedirect(route('cattedre.index'));
        $this->assertDatabaseHas('cattedre', [
            'docente_id' => $docente->id,
            'classe_id' => $classe->id,
            'disciplina_id' => $disciplina->id,
            'ore' => 6,
        ]);
    }

    public function test_la_segreteria_non_puo_gestire_lanagrafica_generale(): void
    {
        $segreteria = User::factory()->create(['ruolo' => 'segreteria']);

        $response = $this->actingAs($segreteria)->post('/sedi', ['nome' => 'Plesso Vietato']);

        $response->assertForbidden();
    }

    public function test_la_segreteria_puo_gestire_i_docenti(): void
    {
        $segreteria = User::factory()->create(['ruolo' => 'segreteria']);

        $response = $this->actingAs($segreteria)->post('/docenti', [
            'nome' => 'Giulia',
            'cognome' => 'Neri',
            'tipo_contratto' => 'tempo_indeterminato',
            'tipo_posto' => 'comune',
            'regime' => 'tempo_pieno',
            'ore_dovute' => 18,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('docenti', ['cognome' => 'Neri']);
    }

    private function seedSlotMattutini(): void
    {
        for ($giorno = 1; $giorno <= 5; $giorno++) {
            for ($ordine = 1; $ordine <= 6; $ordine++) {
                Slot::query()->create([
                    'giorno' => $giorno,
                    'ordine' => $ordine,
                    'inizio' => '08:00:00',
                    'fine' => '08:50:00',
                    'intervallo_dopo' => false,
                ]);
            }
        }
    }
}
