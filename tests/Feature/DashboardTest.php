<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\Orario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_mostra_i_problemi_prima_di_generare_con_il_link_per_correggerli(): void
    {
        $classe = Classe::factory()->create(); // quadro orario senza cattedre: le ore non coincidono

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/dashboard')
            ->assertOk()
            ->assertSee('Sei pronto a generare?')
            ->assertSee('Classe '.$classe->nomeCompleto())
            ->assertSee('href="'.route('classi.edit', $classe).'"', false);
    }

    public function test_mostra_worker_ultima_generazione_ultimo_orario_e_carico_dei_docenti(): void
    {
        $orario = Orario::factory()->create(['punteggio' => 7]);
        $generazione = Generazione::query()->create([
            'periodo_id' => $orario->periodo_id, 'orario_id' => $orario->id, 'seed' => 42, 'time_limit_s' => 60, 'stato' => 'completata',
        ]);
        $docente = Docente::factory()->create(['ore_dovute' => 18]); // nessuna cattedra: 18 ore a disposizione

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/dashboard')
            ->assertOk()
            ->assertSee('Worker di coda:')
            ->assertSee('#'.$generazione->id, false)
            ->assertSee('Ultimo orario')
            ->assertSee(route('orari.export.generale', $orario), false)
            ->assertSee($docente->nomeCompleto())
            ->assertSee('18 a disposizione')
            ->assertSee('Percorso di avvio');
    }

    public function test_il_docente_vede_solo_il_benvenuto(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get('/dashboard')
            ->assertOk()
            ->assertSee('Benvenuto')
            ->assertDontSee('Sei pronto a generare?');
    }
}
