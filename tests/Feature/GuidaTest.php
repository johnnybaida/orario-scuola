<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_guida_e_divisa_in_sezioni_dal_file_markdown(): void
    {
        $risposta = $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->getJson('/guida')->assertOk();

        $titoli = collect($risposta->json('sezioni'))->pluck('titolo');
        $this->assertSame('Introduzione', $titoli->first());
        $this->assertContains('Genera orario', $titoli);
        $this->assertContains('Utenze e ruoli', $titoli);
        // Markdown reso in HTML (la tabella dei ruoli) e nessun "##" rimasto nel testo.
        $utenze = collect($risposta->json('sezioni'))->firstWhere('id', 'utenze-e-ruoli');
        $this->assertStringContainsString('<table>', $utenze['html']);
        $this->assertStringNotContainsString('## ', $utenze['html']);
    }

    public function test_la_guida_richiede_il_login_e_la_pagina_ha_pulsante_e_pannello(): void
    {
        $this->getJson('/guida')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/dashboard')
            ->assertOk()->assertSee('data-apri-guida', false)->assertSee('id="pannello-guida"', false);
    }

    public function test_il_pannello_si_apre_sulla_sezione_della_pagina_e_la_mappa_punta_a_sezioni_esistenti(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));

        $utente->get('/dashboard')->assertSee('data-contesto="per-iniziare"', false);
        $utente->get('/classi')->assertSee('data-contesto="classi"', false);
        $utente->get('/aule')->assertSee('data-contesto="sedi-e-aule"', false);

        $id = collect($utente->getJson('/guida')->json('sezioni'))->pluck('id');
        foreach (array_unique(array_values(\App\Support\Guida::MAPPA)) as $sezione) {
            $this->assertContains($sezione, $id, "La mappa punta a una sezione inesistente: {$sezione}");
        }
    }
}
