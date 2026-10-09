<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\User;
use App\Models\Vincolo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VincoloTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_crea_un_vincolo_d1_valido(): void
    {
        $classe = Classe::factory()->create();
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO',
            'ambito_livello' => 'classe',
            'ambito_ids' => [$classe->id],
            'parametri' => ['disciplina_id' => $disciplina->id, 'min_consecutive' => 2, 'n_blocchi_min' => 1],
            'severita' => 'rigido',
        ]);

        $response->assertRedirect(route('vincoli.index'));
        $this->assertDatabaseHas('vincoli', ['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'severita' => 'rigido']);
    }

    public function test_d1_per_i_docenti_non_richiede_la_disciplina_ma_per_gli_altri_ambiti_si(): void
    {
        $d1 = $this->referente();
        $docenti = Docente::factory()->count(2)->create();
        $base = ['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'severita' => 'rigido', 'parametri' => ['min_consecutive' => 2]];

        $this->actingAs($d1)->post('/vincoli', $base + ['ambito_livello' => 'docente', 'ambito_ids' => $docenti->pluck('id')->all()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vincoli', ['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'ambito_livello' => 'docente']);

        $this->post('/vincoli', $base + ['ambito_livello' => 'globale'])->assertSessionHasErrors('parametri.disciplina_id');
        $this->post('/vincoli', $base + ['ambito_livello' => 'classe', 'ambito_ids' => [Classe::factory()->create()->id]])->assertSessionHasErrors('parametri.disciplina_id');
    }

    public function test_l_elenco_mostra_la_disciplina_del_vincolo(): void
    {
        $disciplina = Disciplina::factory()->create(['nome' => 'Arte e immagine']);
        Vincolo::factory()->create(['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'parametri' => ['disciplina_id' => $disciplina->id, 'max' => 1]]);
        Vincolo::factory()->create(['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'ambito_livello' => 'docente', 'ambito_ids' => [], 'parametri' => ['disciplina_id' => null, 'min_consecutive' => 2]]);
        Vincolo::factory()->create(['tipo' => 'T3_MAX_ORE_BUCHE', 'ambito_livello' => 'globale', 'parametri' => ['max_per_giorno' => 1]]);

        $this->actingAs($this->referente())->get('/vincoli')->assertOk()
            ->assertSee('Disciplina</th>', false)->assertSee('Arte e immagine')->assertSee('Tutte');
    }

    public function test_d1_rifiuta_min_consecutive_sotto_la_soglia(): void
    {
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO',
            'ambito_livello' => 'globale',
            'parametri' => ['disciplina_id' => $disciplina->id, 'min_consecutive' => 1],
            'severita' => 'rigido',
        ]);

        $response->assertSessionHasErrors('parametri.min_consecutive');
    }

    public function test_d6_richiede_almeno_uno_slot(): void
    {
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'D6_FASCIA_ORARIA',
            'ambito_livello' => 'globale',
            'parametri' => ['disciplina_id' => $disciplina->id, 'tipo' => 'vietata', 'slot_ids' => []],
            'severita' => 'rigido',
        ]);

        $response->assertSessionHasErrors('parametri.slot_ids');
    }

    public function test_t2_ambito_docente_e_valido(): void
    {
        $docente = Docente::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'T2_GIORNO_LIBERO',
            'ambito_livello' => 'docente',
            'ambito_ids' => [$docente->id],
            'parametri' => ['n_giorni' => 1, 'preferenze' => [6]],
            'severita' => 'preferenziale',
            'peso' => 50,
        ]);

        $response->assertRedirect(route('vincoli.index'));
        $this->assertDatabaseHas('vincoli', ['tipo' => 'T2_GIORNO_LIBERO', 'peso' => 50]);
    }

    public function test_t2_ambito_classe_non_e_consentito(): void
    {
        $classe = Classe::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'T2_GIORNO_LIBERO',
            'ambito_livello' => 'classe',
            'ambito_ids' => [$classe->id],
            'parametri' => ['n_giorni' => 1],
            'severita' => 'rigido',
        ]);

        $response->assertSessionHasErrors('ambito_livello');
    }

    public function test_t3_richiede_almeno_un_massimo(): void
    {
        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'T3_MAX_ORE_BUCHE',
            'ambito_livello' => 'globale',
            'parametri' => [],
            'severita' => 'preferenziale',
            'peso' => 20,
        ]);

        $response->assertSessionHasErrors(['parametri.max_per_giorno', 'parametri.max_per_settimana']);
    }

    public function test_severita_preferenziale_richiede_peso(): void
    {
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/vincoli', [
            'tipo' => 'D3_MAX_ORE_GIORNO',
            'ambito_livello' => 'globale',
            'parametri' => ['disciplina_id' => $disciplina->id, 'max' => 2],
            'severita' => 'preferenziale',
        ]);

        $response->assertSessionHasErrors('peso');
    }

    public function test_un_docente_non_puo_gestire_i_vincoli(): void
    {
        $docente = User::factory()->create(['ruolo' => 'docente']);

        $response = $this->actingAs($docente)->get('/vincoli');

        $response->assertForbidden();
    }

    public function test_elimina_un_vincolo(): void
    {
        $vincolo = Vincolo::factory()->create();

        $response = $this->actingAs($this->referente())->delete("/vincoli/{$vincolo->id}");

        $response->assertRedirect(route('vincoli.index'));
        $this->assertDatabaseMissing('vincoli', ['id' => $vincolo->id]);
    }

    public function test_d12_si_crea_con_disciplina_o_per_un_docente_senza_e_rifiuta_valori_fuori_soglia(): void
    {
        $classe = Classe::factory()->create();
        $disciplina = Disciplina::factory()->create();
        $docente = \App\Models\Docente::factory()->create();
        $utente = $this->actingAs($this->referente());

        $utente->post('/vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito_livello' => 'classe', 'ambito_ids' => [$classe->id],
            'parametri' => ['disciplina_id' => $disciplina->id, 'max_consecutive' => 2], 'severita' => 'rigido'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO']);

        // per i docenti la disciplina è facoltativa (massimo di ore consecutive del docente); per gli altri ambiti è obbligatoria
        $utente->post('/vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito_livello' => 'docente', 'ambito_ids' => [$docente->id],
            'parametri' => ['max_consecutive' => 4], 'severita' => 'preferenziale', 'peso' => 30])->assertSessionHasNoErrors();
        $utente->post('/vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito_livello' => 'globale',
            'parametri' => ['max_consecutive' => 2], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.disciplina_id');
        $utente->post('/vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito_livello' => 'classe', 'ambito_ids' => [$classe->id],
            'parametri' => ['disciplina_id' => $disciplina->id, 'max_consecutive' => 0], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.max_consecutive');

        $this->assertSame('Tutte le lezioni: al massimo 4 ore consecutive nello stesso giorno.',
            (new \App\Constraints\Tipi\D12BloccoMaxConsecutivo)->descrizione(['max_consecutive' => 4]));
        $this->assertSame('Tutte le lezioni: mai due ore consecutive nello stesso giorno.', (new \App\Constraints\Tipi\D12BloccoMaxConsecutivo)->descrizione(['max_consecutive' => 1]));
    }
}
