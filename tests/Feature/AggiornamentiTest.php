<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AggiornamentiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

    public function test_segnala_il_tag_piu_alto_se_piu_recente_ignorando_i_tag_non_di_versione(): void
    {
        Http::fake(['api.github.com/*' => Http::response([['name' => 'v0.2.0'], ['name' => 'v0.10.1'], ['name' => 'v0.9.0'], ['name' => 'bozza']])]);

        $this->actingAs($this->admin())->getJson('/aggiornamenti')
            ->assertOk()->assertJsonPath('disponibile.versione', '0.10.1')
            ->assertJsonPath('disponibile.url', 'https://github.com/johnnybaida/orario-scuola/releases/tag/v0.10.1');
    }

    public function test_nessun_avviso_se_la_versione_e_aggiornata_o_non_ci_sono_tag(): void
    {
        Http::fake(['api.github.com/*' => Http::response([['name' => 'v0.1.0']])]);
        $this->actingAs($this->admin())->getJson('/aggiornamenti')->assertOk()->assertJsonPath('disponibile', null);

        Cache::flush();
        Http::fake(['api.github.com/*' => Http::response([])]);
        $this->actingAs($this->admin())->getJson('/aggiornamenti')->assertJsonPath('disponibile', null);
    }

    public function test_senza_rete_o_con_errore_non_si_rompe_nulla_e_la_risposta_e_in_cache(): void
    {
        Http::fake(['api.github.com/*' => Http::response('errore', 500)]);
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/aggiornamenti')->assertOk()->assertJsonPath('disponibile', null);
        $this->actingAs($admin)->getJson('/aggiornamenti');
        Http::assertSentCount(1); // il fallimento si ricorda per un'ora
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
