<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Impostazioni;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use App\Models\Vincolo;
use App\Services\SedeCorrente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopiaDaSedeTest extends TestCase
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

    /** Si lavora nella sede B (vuota) mentre la A ha la configurazione. */
    private function utenteInB()
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
        $utente->post('/sede', ['sede_id' => $this->b->id]);

        return $utente;
    }

    public function test_copia_scansione_e_impostazioni_solo_se_la_sede_e_vuota(): void
    {
        $this->in($this->a);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'ricreazione_minuti' => 10, 'ricreazione_nome' => 'Mensa']);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 2, 'inizio' => '09:00:00', 'fine' => '09:50:00']);
        Impostazioni::correnti()->update(['conteggio_sostegno' => 'per_classe']);
        $utente = $this->utenteInB();

        $utente->get('/scansione-oraria')->assertOk()->assertSee('è vuota in questa sede')->assertSee('Centrale (2)');
        $utente->post('/copia-da-sede/scansione', ['sede_id' => $this->a->id])->assertRedirect(route('scansione.index'));

        $this->in($this->b);
        $this->assertSame(2, Slot::query()->count());
        $this->assertDatabaseHas('slot', ['sede_id' => $this->b->id, 'ordine' => 1, 'ricreazione_nome' => 'Mensa']);
        $this->assertSame('per_classe', Impostazioni::correnti()->conteggio_sostegno);
        $this->assertSame(2, Slot::query()->withoutGlobalScopes()->where('sede_id', $this->a->id)->count());   // l'origine non cambia
        $this->assertSame(1, \App\Models\AuditLog::query()->where('azione', 'duplicazione')->count());          // un solo rigo di registro

        // ora l'area non è più vuota: niente offerta e niente copia
        $utente->get('/scansione-oraria')->assertDontSee('è vuota in questa sede');
        $utente->post('/copia-da-sede/scansione', ['sede_id' => $this->a->id])->assertSessionHasErrors('copia');
        $this->assertSame(2, Slot::query()->count());
    }

    public function test_copia_discipline_con_le_discipline_padre(): void
    {
        $this->in($this->a);
        $padre = Disciplina::factory()->create(['codice' => 'LIN', 'nome' => 'Lingue']);
        Disciplina::factory()->create(['codice' => 'FRA', 'nome' => 'Francese', 'padre_id' => $padre->id, 'tipo_aula_richiesto' => 'dada_sec_ling']);
        $utente = $this->utenteInB();

        $utente->post('/copia-da-sede/discipline', ['sede_id' => $this->a->id])->assertRedirect(route('discipline.index'));

        $this->in($this->b);
        $fra = Disciplina::query()->where('codice', 'FRA')->firstOrFail();
        $this->assertSame('dada_sec_ling', $fra->tipo_aula_richiesto);
        $this->assertSame('LIN', $fra->padre->codice);
        $this->assertSame($this->b->id, $fra->padre->sede_id);   // il padre è la copia di questa sede, non l'originale
    }

    public function test_copia_i_quadri_solo_con_le_discipline_gia_presenti(): void
    {
        $this->in($this->a);
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        $quadro = QuadroOrario::factory()->create(['nome' => 'Normale', 'ore_totali' => 6]);
        $quadro->righe()->create(['disciplina_id' => $ita->id, 'ore_settimanali' => 6]);
        $utente = $this->utenteInB();

        $utente->post('/copia-da-sede/quadri', ['sede_id' => $this->a->id])->assertSessionHasErrors('copia');
        $this->in($this->b);
        $this->assertSame(0, QuadroOrario::query()->count());   // niente a metà

        $utente->post('/copia-da-sede/discipline', ['sede_id' => $this->a->id]);
        $utente->post('/copia-da-sede/quadri', ['sede_id' => $this->a->id])->assertSessionHasNoErrors();

        $copia = QuadroOrario::query()->with('righe.disciplina')->firstOrFail();
        $this->assertSame(6, $copia->ore_totali);
        $this->assertSame($this->b->id, $copia->righe->first()->disciplina->sede_id);   // la riga punta alla disciplina di questa sede
    }

    public function test_copia_le_aule_e_i_vincoli_globali_rimappando_discipline_e_ore(): void
    {
        $this->in($this->a);
        Aula::factory()->create(['nome' => 'Lab', 'tipo' => 'laboratorio', 'capienza' => 2, 'piano' => 1]);
        $mat = Disciplina::factory()->create(['codice' => 'MAT']);
        $s1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        Vincolo::factory()->create(['tipo' => 'D6_FASCIA_ORARIA', 'ambito_livello' => 'globale', 'parametri' => ['disciplina_id' => $mat->id, 'tipo' => 'vietata', 'slot_ids' => [$s1->id]]]);
        Vincolo::factory()->create(['tipo' => 'T3_MAX_ORE_BUCHE', 'ambito_livello' => 'docente', 'ambito_ids' => [99], 'parametri' => ['max_per_giorno' => 1]]);
        $utente = $this->utenteInB();

        $utente->post('/copia-da-sede/aule', ['sede_id' => $this->a->id]);
        $this->in($this->b);
        $this->assertDatabaseHas('aule', ['sede_id' => $this->b->id, 'nome' => 'Lab', 'capienza' => 2, 'piano' => 1]);

        // senza discipline e scansione i vincoli che le usano si saltano, e lo dice
        $utente->post('/copia-da-sede/vincoli', ['sede_id' => $this->a->id]);
        $this->assertSame(0, Vincolo::query()->count());
        $this->in($this->a);
        Vincolo::query()->withoutGlobalScopes()->where('sede_id', $this->b->id)->delete();

        $utente->post('/copia-da-sede/discipline', ['sede_id' => $this->a->id]);
        $utente->post('/copia-da-sede/scansione', ['sede_id' => $this->a->id]);
        $this->followingRedirects()->post('/copia-da-sede/vincoli', ['sede_id' => $this->a->id])->assertSee('Non copiati');

        $this->in($this->b);
        $v = Vincolo::query()->firstOrFail();   // il vincolo sui docenti di un'altra sede non c'è
        $this->assertSame([Disciplina::query()->where('codice', 'MAT')->value('id')], $v->parametri['disciplina_ids']);   // l'elenco rimappato sulle discipline della sede
        $this->assertSame([Slot::query()->where('giorno', 1)->where('ordine', 1)->value('id')], $v->parametri['slot_ids']);
        $this->assertSame(1, Vincolo::query()->count());
    }

    public function test_offerta_e_copia_solo_per_chi_gestisce_l_anagrafica_e_con_un_origine_valida(): void
    {
        $this->in($this->a);
        Aula::factory()->create();
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->post('/sede', ['sede_id' => $this->b->id]);
        $this->get('/aule')->assertOk()->assertDontSee('è vuota in questa sede');
        $this->post('/copia-da-sede/aule', ['sede_id' => $this->a->id])->assertForbidden();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->post('/sede', ['sede_id' => $this->b->id]);
        $this->get('/aule')->assertOk()->assertSee('è vuota in questa sede');
        $this->post('/copia-da-sede/aule', ['sede_id' => $this->b->id])->assertSessionHasErrors('copia');   // non da se stessa
        $this->post('/copia-da-sede/inesistente', ['sede_id' => $this->a->id])->assertNotFound();
    }
}
