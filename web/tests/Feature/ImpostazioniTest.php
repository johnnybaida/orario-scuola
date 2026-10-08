<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Impostazioni;
use App\Models\Sede;
use App\Models\User;
use App\Services\SedeCorrente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImpostazioniTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_mostra_e_salva_il_conteggio_predefinito_del_sostegno(): void
    {
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->actingAs($referente)->get('/impostazioni')->assertOk()->assertSee('Conteggio predefinito')->assertSee('Per alunno');
        $this->put('/impostazioni', ['conteggio_sostegno' => 'per_classe'])->assertRedirect(route('impostazioni.index'));

        $this->assertSame('per_classe', Impostazioni::correnti()->conteggio_sostegno);
        $classe = Classe::factory()->create(['conteggio_sostegno' => null]);
        $this->assertSame('per_classe', $classe->conteggioSostegnoEffettivo());   // la classe lo eredita
        $this->assertDatabaseHas('audit_log', ['entita' => 'Impostazioni', 'azione' => 'modifica']);
        $this->put('/impostazioni', ['conteggio_sostegno' => 'boh'])->assertSessionHasErrors('conteggio_sostegno');
    }

    public function test_ogni_sede_ha_le_sue_impostazioni(): void
    {
        $a = Sede::factory()->create();
        $b = Sede::factory()->create();
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));

        $utente->post('/sede', ['sede_id' => $a->id]);
        $utente->put('/impostazioni', ['conteggio_sostegno' => 'per_classe']);
        $utente->post('/sede', ['sede_id' => $b->id]);
        $utente->get('/impostazioni')->assertOk();   // la richiesta successiva lavora nella sede B

        $this->assertSame('per_alunno', Impostazioni::correnti()->conteggio_sostegno);   // l'altra sede non è cambiata
        app(SedeCorrente::class)->imposta($a->id);
        $this->assertSame('per_classe', Impostazioni::correnti()->conteggio_sostegno);
    }

    public function test_chi_consulta_la_vede_in_sola_lettura_e_il_docente_non_la_vede(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/impostazioni')->assertOk()->assertDontSee('Salva');
        $this->put('/impostazioni', ['conteggio_sostegno' => 'per_classe'])->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get('/impostazioni')->assertForbidden();
    }

    public function test_la_durata_dell_ora_e_i_giorni_non_sono_piu_campi_inutilizzati(): void
    {
        $this->assertFalse(Schema::hasColumn('impostazioni', 'durata_ora_minuti'));
        $this->assertFalse(Schema::hasColumn('impostazioni', 'giorni_settimana'));
    }
}
