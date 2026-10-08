<?php

namespace Tests\Feature;

use App\Jobs\GenerateTimetable;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\Orario;
use App\Models\Sede;
use App\Models\User;
use App\Services\SedeCorrente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeCorrenteTest extends TestCase
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

    private function lavoraIn(?Sede $sede): void
    {
        app(SedeCorrente::class)->imposta($sede?->id);
    }

    public function test_i_record_nascono_nella_sede_corrente_e_si_vedono_solo_li(): void
    {
        $this->lavoraIn($this->a);
        $inA = Docente::factory()->create(['cognome' => 'Alfa']);
        $this->lavoraIn($this->b);
        $inB = Docente::factory()->create(['cognome' => 'Beta']);

        $this->assertSame($this->b->id, $inB->sede_id);
        $this->assertSame(['Beta'], Docente::query()->pluck('cognome')->all());
        $this->assertNull(Docente::query()->find($inA->id));

        $this->lavoraIn($this->a);
        $this->assertSame(['Alfa'], Docente::query()->pluck('cognome')->all());

        $this->lavoraIn(null);   // fuori da una richiesta (comandi, seeder) non si filtra
        $this->assertCount(2, Docente::query()->get());
    }

    public function test_senza_sede_impostata_i_nuovi_record_vanno_nella_prima_sede(): void
    {
        $this->lavoraIn(null);

        $this->assertSame($this->a->id, Docente::factory()->create()->sede_id);
    }

    public function test_lo_stesso_codice_disciplina_puo_esistere_in_due_sedi(): void
    {
        $this->lavoraIn($this->a);
        Disciplina::factory()->create(['codice' => 'ITA']);
        $this->lavoraIn($this->b);
        Disciplina::factory()->create(['codice' => 'ITA']);

        $this->lavoraIn(null);
        $this->assertSame(2, Disciplina::query()->where('codice', 'ITA')->count());
    }

    public function test_il_selettore_cambia_sede_la_ricorda_e_isola_le_pagine(): void
    {
        $this->lavoraIn($this->a);
        Docente::factory()->create(['cognome' => 'Alfa']);
        $this->lavoraIn($this->b);
        $inB = Docente::factory()->create(['cognome' => 'Beta']);
        $this->lavoraIn(null);
        $utente = User::factory()->create(['ruolo' => 'referente_orario']);

        // si parte dalla prima sede
        $this->actingAs($utente)->get('/docenti')->assertOk()->assertSee('Alfa')->assertDontSee('Beta')->assertSee('Succursale');
        $this->get("/docenti/{$inB->id}/edit")->assertNotFound();

        $this->post('/sede', ['sede_id' => $this->b->id])->assertRedirect(route('dashboard'));
        $this->get('/docenti')->assertOk()->assertSee('Beta')->assertDontSee('Alfa');
        $this->assertSame($this->b->id, $utente->fresh()->ultima_sede_id);

        // nuova sessione: riparte dall'ultima sede usata
        $this->flushSession();
        $this->actingAs($utente->fresh())->get('/docenti')->assertSee('Beta')->assertDontSee('Alfa');
        $this->post('/sede', ['sede_id' => 9999])->assertNotFound();
    }

    public function test_con_una_sola_sede_il_selettore_non_compare(): void
    {
        $this->b->delete();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/dashboard')->assertOk()->assertDontSee('id="sede-corrente"', false);
    }

    public function test_la_generazione_lavora_nella_sede_della_propria_generazione(): void
    {
        $this->lavoraIn($this->b);
        $orario = Orario::factory()->create();
        $generazione = Generazione::query()->create(['periodo_id' => $orario->periodo_id, 'seed' => 1, 'time_limit_s' => 10, 'stato' => 'in_coda']);
        $this->lavoraIn(null);

        app()->call([new GenerateTimetable($generazione->id), 'handle']);

        $this->assertSame($this->b->id, app(SedeCorrente::class)->id());
    }
}
