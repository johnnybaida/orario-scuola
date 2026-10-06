<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AggiornamentiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.versione' => '0.1.0', 'app.controllo_aggiornamenti' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['ruolo' => 'amministratore']);
    }

    public function test_la_versione_compare_nella_sidebar(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee('Versione 0.1.0');
    }

    private const URL_VERSION = 'raw.githubusercontent.com/johnnybaida/orario-scuola/main/VERSION*';

    public function test_segnala_una_versione_piu_alta_nel_file_version_online_senza_bisogno_di_tag(): void
    {
        Http::fake([self::URL_VERSION => Http::response("0.10.1\n")]);   // 0.10.1 > 0.1.0: confronto numerico, non alfabetico

        $this->actingAs($this->admin())->getJson('/aggiornamenti')
            ->assertOk()->assertJsonPath('disponibile.versione', '0.10.1')
            ->assertJsonPath('disponibile.url', 'https://github.com/johnnybaida/orario-scuola');
        Http::assertSent(fn ($richiesta) => str_contains($richiesta->url(), 'main/VERSION'));
    }

    public function test_nessun_avviso_se_la_versione_e_uguale_inferiore_o_il_file_non_e_valido(): void
    {
        $admin = $this->admin();
        foreach (['0.1.0', '0.0.9', 'non una versione', '', '<html>404</html>'] as $contenuto) {
            Http::fake([self::URL_VERSION => Http::response($contenuto)]);
            $this->actingAs($admin)->getJson('/aggiornamenti')->assertOk()->assertJsonPath('disponibile', null);
        }
    }

    public function test_senza_rete_o_con_errore_non_si_rompe_nulla_e_non_c_e_cache(): void
    {
        Http::fake([self::URL_VERSION => Http::sequence()->push('errore', 500)->push('0.2.0')->push('0.3.0')]);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/aggiornamenti')->assertOk()->assertJsonPath('disponibile', null);
        $this->actingAs($admin)->getJson('/aggiornamenti')->assertJsonPath('disponibile.versione', '0.2.0');
        $this->actingAs($admin)->getJson('/aggiornamenti')->assertJsonPath('disponibile.versione', '0.3.0');
    }

    public function test_solo_la_dashboard_avvia_il_controllo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/dashboard')->assertSee('data-controlla', false);
        $this->actingAs($admin)->get('/utenze')->assertDontSee('data-controlla', false);
    }

    public function test_si_disattiva_e_non_e_per_tutti_i_ruoli(): void
    {
        config(['app.controllo_aggiornamenti' => false]);
        Http::fake();
        $this->actingAs($this->admin())->getJson('/aggiornamenti')->assertJsonPath('disponibile', null);
        Http::assertNothingSent();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->getJson('/aggiornamenti')->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/dashboard')->assertDontSee('data-aggiornamenti', false);
    }
}
