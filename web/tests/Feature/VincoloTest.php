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

        $this->post('/vincoli', $base + ['ambito_livello' => 'globale'])->assertSessionHasErrors('parametri.disciplina_ids');
        $this->post('/vincoli', $base + ['ambito_livello' => 'classe', 'ambito_ids' => [Classe::factory()->create()->id]])->assertSessionHasErrors('parametri.disciplina_ids');
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
            'parametri' => ['max_consecutive' => 2], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.disciplina_ids');
        $utente->post('/vincoli', ['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'ambito_livello' => 'classe', 'ambito_ids' => [$classe->id],
            'parametri' => ['disciplina_id' => $disciplina->id, 'max_consecutive' => 0], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.max_consecutive');

        $this->assertSame('Tutte le lezioni: al massimo 4 ore consecutive nello stesso giorno.',
            (new \App\Constraints\Tipi\D12BloccoMaxConsecutivo)->descrizione(['max_consecutive' => 4]));
        $this->assertSame('Tutte le lezioni: mai due ore consecutive nello stesso giorno.', (new \App\Constraints\Tipi\D12BloccoMaxConsecutivo)->descrizione(['max_consecutive' => 1]));
    }

    public function test_una_regola_vale_per_piu_discipline_e_il_vecchio_singolo_id_si_converte(): void
    {
        $classe = Classe::factory()->create();
        [$ita, $mat, $art] = Disciplina::factory()->count(3)->create();
        $utente = $this->actingAs($this->referente());

        $utente->post('/vincoli', ['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'severita' => 'rigido',
            'parametri' => ['disciplina_ids' => [$ita->id, $mat->id], 'max' => 2]])->assertSessionHasNoErrors();
        $vincolo = \App\Models\Vincolo::query()->latest('id')->first();
        $this->assertSame([$ita->id, $mat->id], array_map('intval', $vincolo->parametri['disciplina_ids']));

        // chi manda ancora il singolo disciplina_id ottiene l'elenco di una disciplina
        $utente->post('/vincoli', ['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'severita' => 'rigido', 'parametri' => ['disciplina_id' => $art->id, 'max' => 1]])->assertSessionHasNoErrors();
        $vecchio = \App\Models\Vincolo::query()->latest('id')->first();
        $this->assertSame([$art->id], array_map('intval', $vecchio->parametri['disciplina_ids']));
        $this->assertArrayNotHasKey('disciplina_id', $vecchio->parametri);

        // D3 e D6 vogliono almeno una disciplina
        $utente->post('/vincoli', ['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'severita' => 'rigido', 'parametri' => ['max' => 2]])->assertSessionHasErrors('parametri.disciplina_ids');

        // l'elenco, la descrizione e il problema per il solver (codici, un elenco)
        $utente->get('/vincoli')->assertOk()->assertSee($ita->nome.', '.$mat->nome, false);
        $this->assertStringContainsString($ita->nome, (new \App\Constraints\Tipi\D3MaxOreGiorno)->descrizione($vincolo->parametri));
        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10);
        $this->assertEqualsCanonicalizing([$ita->codice, $mat->codice], $problema['vincoli'][0]['parametri']['discipline']);
        $this->assertArrayNotHasKey('disciplina', $problema['vincoli'][0]['parametri']);
    }

    public function test_la_migrazione_converte_i_vincoli_esistenti_all_elenco_di_discipline_ed_e_reversibile(): void
    {
        $disciplina = Disciplina::factory()->create();
        $vincolo = \App\Models\Vincolo::factory()->create(['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'parametri' => ['disciplina_id' => (string) $disciplina->id, 'max' => '2']]);
        $docente = \App\Models\Vincolo::factory()->create(['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'ambito_livello' => 'docente', 'parametri' => ['disciplina_id' => '', 'min_consecutive' => '2']]);
        $migrazione = require database_path('migrations/2026_10_10_090000_vincoli_disciplina_ids.php');

        $migrazione->up();
        $this->assertSame([$disciplina->id], $vincolo->fresh()->parametri['disciplina_ids']);
        $this->assertArrayNotHasKey('disciplina_id', $vincolo->fresh()->parametri);
        $this->assertSame([], \App\Constraints\DisciplineVincolo::ids($docente->fresh()->parametri));   // nessuna = qualsiasi (docente)

        $migrazione->down();
        $this->assertSame((string) $disciplina->id, (string) $vincolo->fresh()->parametri['disciplina_id']);
    }

    public function test_s5_distribuzione_del_sostegno_chiede_almeno_un_limite_e_vale_per_globale_o_classe(): void
    {
        $classe = Classe::factory()->create();
        $docente = \App\Models\Docente::factory()->create();
        $utente = $this->actingAs($this->referente());

        $utente->post('/vincoli', ['tipo' => 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito_livello' => 'globale', 'parametri' => ['max_insieme' => 1, 'tolleranza_giorno' => 1], 'severita' => 'preferenziale', 'peso' => 40])->assertSessionHasNoErrors();
        $utente->post('/vincoli', ['tipo' => 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito_livello' => 'classe', 'ambito_ids' => [$classe->id], 'parametri' => ['tolleranza_giorno' => 0], 'severita' => 'rigido'])->assertSessionHasNoErrors();
        $utente->post('/vincoli', ['tipo' => 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito_livello' => 'globale', 'parametri' => ['max_insieme' => '', 'tolleranza_giorno' => ''], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.max_insieme');
        $utente->post('/vincoli', ['tipo' => 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito_livello' => 'docente', 'ambito_ids' => [$docente->id], 'parametri' => ['max_insieme' => 1], 'severita' => 'rigido'])->assertSessionHasErrors('ambito_livello');
        $utente->post('/vincoli', ['tipo' => 'S5_DISTRIBUZIONE_SOSTEGNO', 'ambito_livello' => 'globale', 'parametri' => ['max_insieme' => 0], 'severita' => 'rigido'])->assertSessionHasErrors('parametri.max_insieme');

        $this->assertSame('Al massimo 1 docente/i di sostegno insieme nella stessa classe e ora; ore di sostegno distribuite nella settimana (al massimo la media giornaliera + 1 ora/e per giorno).',
            (new \App\Constraints\Tipi\S5DistribuzioneSostegno)->descrizione(['max_insieme' => 1, 'tolleranza_giorno' => 1]));
    }

    public function test_un_vincolo_si_disattiva_dal_form_e_non_arriva_piu_al_solver(): void
    {
        $utente = $this->actingAs($this->referente());
        $utente->get('/vincoli/create')->assertOk()->assertSee('name="attivo" value="0"', false);

        // il form invia attivo=0 (campo nascosto) quando la casella non è spuntata, attivo=1 quando lo è
        $utente->post('/vincoli', ['tipo' => 'T2_GIORNO_LIBERO', 'ambito_livello' => 'globale', 'parametri' => ['n_giorni' => 1], 'severita' => 'rigido', 'attivo' => '0'])->assertSessionHasNoErrors();
        $vincolo = \App\Models\Vincolo::query()->latest('id')->first();
        $this->assertFalse($vincolo->attivo);
        $this->assertSame([], app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10)['vincoli']);

        $utente->put("/vincoli/{$vincolo->id}", ['tipo' => 'T2_GIORNO_LIBERO', 'ambito_livello' => 'globale', 'parametri' => ['n_giorni' => 1], 'severita' => 'rigido', 'attivo' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($vincolo->fresh()->attivo);
        $this->assertCount(1, app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10)['vincoli']);

        $utente->put("/vincoli/{$vincolo->id}", ['tipo' => 'T2_GIORNO_LIBERO', 'ambito_livello' => 'globale', 'parametri' => ['n_giorni' => 1], 'severita' => 'rigido', 'attivo' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($vincolo->fresh()->attivo);
    }

    public function test_t11_ore_in_fascia_vale_per_i_docenti_scelti_con_slot_e_ore_minime(): void
    {
        $docente = \App\Models\Docente::factory()->create();
        $slot = \App\Models\Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $utente = $this->actingAs($this->referente());
        $base = ['tipo' => 'T11_ORE_IN_FASCIA', 'ambito_livello' => 'docente', 'ambito_ids' => [$docente->id], 'severita' => 'rigido'];

        $utente->get('/vincoli/create')->assertOk()->assertSee('T11_ORE_IN_FASCIA', false)->assertSee('Ore minime in una fascia', false);
        $utente->post('/vincoli', $base + ['parametri' => ['min_ore' => 1, 'slot_ids' => [$slot->id]]])->assertSessionHasNoErrors();
        $utente->post('/vincoli', $base + ['parametri' => ['min_ore' => 0, 'slot_ids' => [$slot->id]]])->assertSessionHasErrors('parametri.min_ore');
        $utente->post('/vincoli', $base + ['parametri' => ['min_ore' => 1]])->assertSessionHasErrors('parametri.slot_ids');
        $utente->post('/vincoli', ['ambito_livello' => 'globale', 'ambito_ids' => []] + $base + ['parametri' => ['min_ore' => 1, 'slot_ids' => [$slot->id]]])->assertSessionHasErrors('ambito_livello');

        $vincolo = \App\Models\Vincolo::query()->where('tipo', 'T11_ORE_IN_FASCIA')->firstOrFail();
        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10)['vincoli'][0];
        $this->assertSame([$docente->id], $problema['ambito']['ids']);
        $this->assertSame([$slot->id], $problema['parametri']['slot_ids']);
        $this->assertSame(1, $problema['parametri']['min_ore']);
        $this->assertSame('Almeno 2 ore tra gli slot selezionati (3).', (new \App\Constraints\Tipi\T11OreInFascia)->descrizione(['min_ore' => 2, 'slot_ids' => [1, 2, 3]]));
    }

    public function test_d13_disciplina_seguita_da_un_altra_con_modo_e_coppie_e_arriva_al_solver_coi_codici(): void
    {
        [$ita, $sto] = Disciplina::factory()->count(2)->create();
        $utente = $this->actingAs($this->referente());
        $base = ['tipo' => 'D13_DISCIPLINA_SEGUITA', 'ambito_livello' => 'globale', 'severita' => 'preferenziale', 'peso' => 30];

        $utente->get('/vincoli/create')->assertOk()->assertSee('parametri[seguite_ids][]', false)->assertSee('Disciplina che segue', false);
        $utente->post('/vincoli', $base + ['parametri' => ['disciplina_ids' => [$ita->id], 'seguite_ids' => [$sto->id], 'modo' => 'segue', 'min_coppie' => '2']])->assertSessionHasNoErrors();
        $utente->post('/vincoli', $base + ['parametri' => ['disciplina_ids' => [$ita->id], 'modo' => 'segue']])->assertSessionHasErrors('parametri.seguite_ids');
        $utente->post('/vincoli', $base + ['parametri' => ['disciplina_ids' => [$ita->id], 'seguite_ids' => [$sto->id], 'modo' => 'boh']])->assertSessionHasErrors('parametri.modo');
        $utente->post('/vincoli', array_replace($base, ['ambito_livello' => 'docente', 'ambito_ids' => [1]]) + ['parametri' => ['disciplina_ids' => [$ita->id], 'seguite_ids' => [$sto->id], 'modo' => 'segue']])->assertSessionHasErrors('ambito_livello');

        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10)['vincoli'][0]['parametri'];
        $this->assertSame([$ita->codice], $problema['discipline']);
        $this->assertSame([$sto->codice], $problema['discipline_seguite']);
        $this->assertSame(2, $problema['min_coppie']);
        $this->assertArrayNotHasKey('seguite_ids', $problema);

        $d = new \App\Constraints\Tipi\D13DisciplinaSeguita;
        $this->assertStringContainsString('almeno 2 volte', $d->descrizione(['disciplina_ids' => [$ita->id], 'seguite_ids' => [$sto->id], 'modo' => 'segue', 'min_coppie' => 2]));
        $this->assertStringContainsString('mai seguita', $d->descrizione(['disciplina_ids' => [$ita->id], 'seguite_ids' => [$sto->id], 'modo' => 'non_segue']));
    }
}
