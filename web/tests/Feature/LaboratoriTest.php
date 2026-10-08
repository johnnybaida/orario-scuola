<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Docente;
use App\Models\Laboratorio;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use App\Services\Editor\ControlloOrario;
use App\Services\Laboratori;
use App\Services\Solver\ProblemBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaboratoriTest extends TestCase
{
    use RefreshDatabase;

    private Slot $mattina;
    private Slot $pomeriggio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mattina = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
        $this->pomeriggio = Slot::factory()->create(['giorno' => 1, 'ordine' => 7, 'inizio' => '14:00:00', 'fine' => '14:50:00']);
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    private function laboratorio(Docente $docente, ?Aula $aula = null, string $nome = 'Latino'): Laboratorio
    {
        $lab = Laboratorio::factory()->create(['nome' => $nome, 'aula_id' => $aula?->id]);
        $lab->docenti()->sync([$docente->id]);
        $lab->slot()->sync([$this->pomeriggio->id]);

        return $lab;
    }

    public function test_si_crea_un_laboratorio_solo_nelle_ore_del_pomeriggio(): void
    {
        $docente = Docente::factory()->create();
        $dati = ['nome' => 'Latino', 'docenti' => [$docente->id], 'slot_ids' => [$this->pomeriggio->id], 'attivo' => 1];

        $this->actingAs($this->referente())->post('/laboratori', $dati)->assertSessionHasNoErrors();
        $lab = Laboratorio::query()->with('slot', 'docenti')->firstOrFail();
        $this->assertSame('Lun 7ª', $lab->quando());
        $this->assertSame([$docente->id], $lab->docenti->pluck('id')->all());
        $this->assertDatabaseHas('audit_log', ['entita' => 'Laboratorio', 'azione' => 'creazione']);

        $this->post('/laboratori', ['slot_ids' => [$this->mattina->id]] + $dati)->assertSessionHasErrors('slot_ids.0');
        $this->post('/laboratori', ['docenti' => []] + $dati)->assertSessionHasErrors('docenti');

        $this->get('/laboratori')->assertOk()->assertSee('Latino')->assertSee('Lun 7ª');
        $this->put("/laboratori/{$lab->id}", ['nome' => 'Latino 2'] + $dati)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('laboratori', ['nome' => 'Latino 2']);
        $this->delete("/laboratori/{$lab->id}")->assertRedirect(route('laboratori.index'));
        $this->assertDatabaseCount('laboratori', 0);
    }

    public function test_solo_chi_gestisce_l_anagrafica_modifica_i_laboratori(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/laboratori')->assertOk()->assertDontSee('Nuovo laboratorio');
        $this->post('/laboratori', ['nome' => 'X'])->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))->get('/laboratori/create')->assertOk();
        $this->post('/laboratori', ['nome' => 'X'])->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get('/laboratori')->assertForbidden();
    }

    public function test_arrivano_al_solver_come_occupazioni_fisse_e_quelli_spenti_no(): void
    {
        $docente = Docente::factory()->create();
        $aula = Aula::factory()->create();
        $lab = $this->laboratorio($docente, $aula);

        $problema = app(ProblemBuilder::class)->costruisci(1, 10);
        $this->assertSame([['docente' => $docente->id, 'slot' => $this->pomeriggio->id, 'aula' => $aula->id, 'laboratorio' => $lab->id]], $problema['occupazioni_fisse']);

        $lab->update(['attivo' => false]);
        $this->assertSame([], app(ProblemBuilder::class)->costruisci(1, 10)['occupazioni_fisse']);
    }

    public function test_il_controllo_segnala_il_laboratorio_che_si_sovrappone_a_una_lezione(): void
    {
        $docente = Docente::factory()->create(['cognome' => 'Rossi']);
        $this->laboratorio($docente);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['docente_id' => $docente->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $this->pomeriggio->id]);

        $testi = array_column(app(ControlloOrario::class)->problemi($orario), 'testo');

        $this->assertTrue(collect($testi)->contains(fn ($t) => str_contains($t, 'Rossi') && str_contains($t, 'laboratorio «Latino»')));
    }

    public function test_la_disponibilita_segnala_docente_con_lezione_aula_occupata_e_altri_laboratori(): void
    {
        $docente = Docente::factory()->create(['cognome' => 'Bruni']);
        $aula = Aula::factory()->create(['capienza' => 1]);
        $altro = $this->laboratorio(Docente::factory()->create(), $aula, 'Teatro');
        $orario = Orario::factory()->create(['stato' => 'pubblicato']);
        $cattedra = Cattedra::factory()->create(['docente_id' => $docente->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $this->pomeriggio->id]);

        $motivi = app(Laboratori::class)->disponibilita([$docente->id], $aula->id);
        $this->assertCount(2, $motivi[$this->pomeriggio->id]);   // lezione del docente + aula occupata dall'altro laboratorio
        $this->assertArrayNotHasKey($this->mattina->id, $motivi); // solo le ore del pomeriggio

        // modificando proprio quel laboratorio non si conflitta con se stessi
        $this->assertSame([], app(Laboratori::class)->disponibilita([], $aula->id, $altro->id)[$this->pomeriggio->id]);
        $this->actingAs($this->referente())->getJson('/laboratori-disponibilita?docenti[]='.$docente->id)->assertOk()->assertJsonCount(1);
    }

    public function test_compaiono_nell_orario_del_docente_e_nel_carico_della_dashboard(): void
    {
        $docente = Docente::factory()->create();
        $this->laboratorio($docente, null, 'Latino');
        $orario = Orario::factory()->create();
        $this->actingAs($this->referente());

        $this->get("/orari/{$orario->id}/docente/{$docente->id}")->assertOk()->assertSee('Laboratori pomeridiani')->assertSee('Lun 7ª · Latino');
        $this->get('/dashboard')->assertOk()->assertSee("laboratori 50'", false);
    }
}
