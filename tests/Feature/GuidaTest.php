<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidaTest extends TestCase
{
    use RefreshDatabase;

    private function sezioni(string $ruolo): \Illuminate\Support\Collection
    {
        return collect($this->actingAs(User::factory()->create(['ruolo' => $ruolo]))->getJson('/guida')->assertOk()->json('sezioni'));
    }

    public function test_la_guida_e_divisa_in_sezioni_dal_file_markdown(): void
    {
        $sezioni = $this->sezioni('amministratore');

        $titoli = $sezioni->pluck('titolo');
        $this->assertSame('Introduzione', $titoli->first());
        $this->assertContains('Genera orario', $titoli);
        $this->assertContains('Utenze', $titoli);
        // Markdown reso in HTML (la tabella dei ruoli) e nessun "##" né marcatore rimasto nel testo.
        $ruoli = $sezioni->firstWhere('id', 'ruoli-e-permessi');
        $this->assertStringContainsString('<table>', $ruoli['html']);
        $this->assertStringNotContainsString('## ', $ruoli['html']);
        $this->assertStringNotContainsString('<!--', $sezioni->pluck('html')->implode(''));
        $this->assertStringContainsString('Amministratore', $ruoli['html']); // {ruolo} sostituito
        $this->assertStringNotContainsString('{ruolo}', $sezioni->pluck('html')->implode(''));
    }

    public function test_la_guida_mostra_solo_cio_che_il_ruolo_puo_vedere_e_fare(): void
    {
        // Il docente non consulta le anagrafiche: niente sezioni operative.
        $docente = $this->sezioni('docente')->pluck('titolo');
        $this->assertContains('Ruoli e permessi', $docente);
        $this->assertContains('Glossario', $docente);
        $this->assertNotContains('Genera orario', $docente);
        $this->assertNotContains('Docenti', $docente);

        // Il dirigente consulta ma non modifica: vede le sezioni, non i blocchi per chi gestisce.
        $dirigente = $this->sezioni('ds');
        $this->assertContains('Genera orario', $dirigente->pluck('titolo'));
        $this->assertStringNotContainsString('Didattica DADA', $dirigente->firstWhere('id', 'sedi-e-aule')['html']);
        $this->assertStringNotContainsString('Esempi di utilizzo', $dirigente->firstWhere('id', 'vincoli')['html']);
        $this->assertNotContains('Utenze', $dirigente->pluck('titolo'));

        // La segreteria gestisce docenti e classi, ma non l'anagrafica generale.
        $segreteria = $this->sezioni('segreteria');
        $this->assertStringContainsString('Salva con il pulsante', $segreteria->firstWhere('id', 'docenti')['html']);
        $this->assertStringNotContainsString('Didattica DADA', $segreteria->firstWhere('id', 'sedi-e-aule')['html']);

        // Il referente orario gestisce l'anagrafica.
        $this->assertStringContainsString('Didattica DADA', $this->sezioni('referente_orario')->firstWhere('id', 'sedi-e-aule')['html']);
    }

    public function test_la_guida_richiede_il_login_e_la_pagina_ha_pulsante_e_pannello(): void
    {
        $this->getJson('/guida')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/dashboard')
            ->assertOk()->assertSee('data-apri-guida', false)->assertSee('id="pannello-guida"', false);
    }

    public function test_il_pannello_si_apre_sulla_sezione_della_pagina_e_la_mappa_punta_a_sezioni_esistenti(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));

        $utente->get('/dashboard')->assertSee('data-contesto="per-iniziare"', false);
        $utente->get('/classi')->assertSee('data-contesto="classi"', false);
        $utente->get('/aule')->assertSee('data-contesto="sedi-e-aule"', false);

        $id = collect($utente->getJson('/guida')->json('sezioni'))->pluck('id');
        foreach (array_unique(array_values(\App\Support\Guida::MAPPA)) as $sezione) {
            $this->assertContains($sezione, $id, "La mappa punta a una sezione inesistente: {$sezione}");
        }
    }
}
