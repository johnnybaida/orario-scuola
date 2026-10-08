<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\Sospensione;
use App\Models\User;
use App\Services\SedeCorrente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeAnagraficheTest extends TestCase
{
    use RefreshDatabase;

    private Sede $a;

    private Sede $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Sede::factory()->create(['nome' => 'Centrale']);
        $this->b = Sede::factory()->create(['nome' => 'Succursale']);
    }

    private function in(Sede $sede): void
    {
        app(SedeCorrente::class)->imposta($sede->id);
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_le_tabelle_figlie_seguono_la_sede_del_genitore(): void
    {
        $this->in($this->a);
        $cattedraA = Cattedra::factory()->create();
        $sospensioneA = Sospensione::query()->create(['docente_id' => $cattedraA->docente_id, 'dal' => now(), 'motivo' => 'malattia', 'esclude_da_orario' => true]);
        $this->in($this->b);
        $cattedraB = Cattedra::factory()->create();

        $this->assertSame([$cattedraB->id], Cattedra::query()->pluck('id')->all());
        $this->assertSame(0, Sospensione::query()->count());
        $this->in($this->a);
        $this->assertSame([$cattedraA->id], Cattedra::query()->pluck('id')->all());
        $this->assertSame([$sospensioneA->id], Sospensione::query()->pluck('id')->all());
        $this->actingAs($this->referente())->get('/cattedre')->assertOk()->assertSee($cattedraA->docente->cognome)->assertDontSee($cattedraB->docente->cognome);
    }

    public function test_il_codice_disciplina_e_unico_per_sede_anche_dai_form(): void
    {
        $this->in($this->a);
        Disciplina::factory()->create(['codice' => 'ITA']);
        $utente = $this->actingAs($this->referente());

        $utente->post('/discipline', ['codice' => 'ITA', 'nome' => 'Italiano'])->assertSessionHasErrors('codice');   // nella stessa sede no

        $utente->post('/sede', ['sede_id' => $this->b->id]);
        $utente->post('/discipline', ['codice' => 'ITA', 'nome' => 'Italiano'])->assertSessionHasNoErrors();           // in un'altra sì
        $this->assertSame(2, Disciplina::query()->withoutGlobalScopes()->where('codice', 'ITA')->count());
    }

    public function test_non_si_possono_usare_in_una_sede_i_record_di_un_altra(): void
    {
        $this->in($this->a);
        $docenteA = Docente::factory()->create();
        $this->in($this->b);
        $classeB = Classe::factory()->create();
        $disciplinaB = Disciplina::factory()->create();

        // si lavora nella sede B ma l'id del docente è della sede A
        $this->actingAs($this->referente())->post('/sede', ['sede_id' => $this->b->id]);
        $this->post('/cattedre', ['docente_id' => $docenteA->id, 'classe_id' => $classeB->id, 'disciplina_id' => $disciplinaB->id, 'ore' => 2])
            ->assertSessionHasErrors('docente_id');
        $this->assertDatabaseCount('cattedre', 0);
    }

    public function test_classi_e_aule_nascono_nella_sede_corrente_senza_scegliere_la_sede(): void
    {
        $quadro = QuadroOrario::factory()->create();   // nella sede predefinita (la prima)
        $utente = $this->actingAs($this->referente());

        $utente->get('/classi/create')->assertOk()->assertDontSee('id="sede_id"', false);
        $utente->get('/aule/create')->assertOk()->assertDontSee('id="sede_id"', false);
        $utente->post('/classi', ['anno_corso' => 1, 'sezione' => 'A', 'quadro_orario_id' => $quadro->id, 'tempo_scuola' => 'normale', 'n_alunni' => 20])->assertSessionHasNoErrors();
        $utente->post('/aule', ['nome' => 'Aula 1', 'tipo' => 'classe', 'capienza' => 1])->assertSessionHasNoErrors();

        $this->assertSame($this->a->id, Classe::query()->firstOrFail()->sede_id);
        $this->assertDatabaseHas('aule', ['nome' => 'Aula 1', 'sede_id' => $this->a->id]);

        // la stessa sezione si può usare in un'altra sede, non due volte nella stessa
        $utente->post('/classi', ['anno_corso' => 1, 'sezione' => 'A', 'quadro_orario_id' => $quadro->id, 'tempo_scuola' => 'normale', 'n_alunni' => 20])->assertSessionHasErrors('sezione');
    }

    public function test_una_sede_senza_scansione_ne_crea_una_standard_una_volta_sola(): void
    {
        $this->in($this->a);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $utente = $this->actingAs($this->referente());

        $utente->get('/scansione-oraria')->assertOk()->assertDontSee('Crea la scansione standard');
        $utente->post('/sede', ['sede_id' => $this->b->id]);
        $utente->get('/scansione-oraria')->assertOk()->assertSee('non ha ancora una scansione oraria')->assertSee('Crea la scansione standard');

        $utente->post('/scansione-oraria/standard')->assertRedirect(route('scansione.index'));
        $this->assertSame(45, Slot::query()->count());   // 5 giorni × 9 ore, solo nella sede B
        $this->assertSame(1, Slot::query()->withoutGlobalScopes()->where('sede_id', $this->a->id)->count());
        $this->assertDatabaseHas('audit_log', ['entita' => 'ScansioneOraria', 'azione' => 'creazione']);
        $utente->post('/scansione-oraria/standard')->assertStatus(422);
    }

    public function test_l_ultima_sede_non_si_elimina(): void
    {
        $utente = $this->actingAs($this->referente());

        $utente->delete(route('sedi.destroy', $this->b))->assertRedirect();
        $utente->delete(route('sedi.destroy', $this->a))->assertStatus(422);
        $this->assertDatabaseCount('sedi', 1);
    }
}
