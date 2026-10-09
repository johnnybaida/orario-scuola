<?php

namespace Tests\Feature;

use App\Models\Docente;
use App\Models\Slot;
use App\Models\User;
use App\Services\AssistenzaPause;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistenzaPauseTest extends TestCase
{
    use RefreshDatabase;

    private function scuola(): void
    {
        foreach ([1, 2] as $giorno) {
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'intervallo_dopo' => true, 'ricreazione_minuti' => 40, 'ricreazione_nome' => 'Mensa']);
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 2, 'inizio' => '09:30:00', 'fine' => '10:20:00']);
        }
    }

    private function salva(User $utente, Docente $docente, array $assistenze)
    {
        return $this->actingAs($utente)->put("/docenti/{$docente->id}", [
            'nome' => $docente->nome, 'cognome' => $docente->cognome, 'tipo_contratto' => $docente->tipo_contratto,
            'tipo_posto' => $docente->tipo_posto, 'regime' => $docente->regime, 'ore_dovute' => $docente->ore_dovute,
            'assistenze_inviate' => 1, 'assistenze' => $assistenze,
        ]);
    }

    public function test_si_assegna_l_assistenza_alla_mensa_dalla_scheda_del_docente(): void
    {
        $this->scuola();
        $docente = Docente::factory()->create();
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->salva($referente, $docente, [['giorno' => 2, 'ordine' => 1]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('assistenze_pausa', ['docente_id' => $docente->id, 'giorno' => 2, 'ordine' => 1]);
        $this->assertDatabaseHas('audit_log', ['entita' => 'AssistenzaPausa', 'azione' => 'creazione']);
        $this->assertSame(['Mar · Mensa 08:50–09:30'], app(AssistenzaPause::class)->elenco($docente->fresh()));
        $this->assertSame(40, app(AssistenzaPause::class)->minuti($docente->fresh()));

        $this->get("/docenti/{$docente->id}/edit")->assertOk()->assertSee('Assistenza alle pause')->assertSee('Mensa 08:50–09:30 (dopo la 1ª ora)');

        // una pausa che non esiste, un giorno inventato e una riga doppia sono rifiutati
        $this->salva($referente, $docente, [['giorno' => 1, 'ordine' => 2]])->assertSessionHasErrors('assistenze.0.ordine');
        $this->salva($referente, $docente, [['giorno' => 9, 'ordine' => 1]])->assertSessionHasErrors('assistenze.0.giorno');
        $this->salva($referente, $docente, [['giorno' => 1, 'ordine' => 1], ['giorno' => 1, 'ordine' => 1]])->assertSessionHasErrors('assistenze');
        $this->assertDatabaseCount('assistenze_pausa', 1);

        // riga assente = eliminata
        $this->salva($referente, $docente, [])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('assistenze_pausa', 0);
    }

    public function test_la_segreteria_non_assegna_l_assistenza_e_l_avviso_segnala_l_indisponibilita_totale(): void
    {
        $this->scuola();
        $docente = Docente::factory()->create();
        $this->salva(User::factory()->create(['ruolo' => 'segreteria']), $docente, [['giorno' => 1, 'ordine' => 1]])->assertForbidden();

        $docente->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);
        $docente->indisponibilita()->sync(Slot::query()->where('giorno', 1)->pluck('id'));
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get("/docenti/{$docente->id}/edit")
            ->assertOk()->assertSee('indisponibile tutto il giorno');
    }

    public function test_l_assistenza_compare_nell_orario_del_docente_e_nel_carico_della_dashboard(): void
    {
        $this->scuola();
        $docente = Docente::factory()->create(['ore_dovute' => 18]);
        $docente->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);
        $orario = \App\Models\Orario::factory()->create();
        $utente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->actingAs($utente)->get("/orari/{$orario->id}/docente/{$docente->id}")->assertOk()->assertSee('Assistenza alle pause')->assertSee('Lun · Mensa 08:50–09:30');
        $this->get('/dashboard')->assertOk()->assertSee("assistenza 40'", false);
    }

    public function test_l_assistenza_conta_nelle_ore_assegnate_del_docente(): void
    {
        $this->scuola();
        $docente = Docente::factory()->create(['ore_dovute' => 18]);
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);
        $this->salva($referente, $docente, [['giorno' => 1, 'ordine' => 1], ['giorno' => 2, 'ordine' => 1]]);   // 2 x 40' contano 2 x 45' (arrotondati al quarto d'ora) = 1,5 ore

        $this->assertSame(1.5, app(AssistenzaPause::class)->ore($docente->fresh()));
        $this->assertSame('1,33', AssistenzaPause::formatta(1.33));
        $this->assertSame('18', AssistenzaPause::formatta(18.0));
        $this->actingAs($referente)->get('/docenti')->assertOk()->assertSee('1,5 / 18');
        $this->actingAs($referente)->get("/docenti/{$docente->id}/edit")->assertOk()->assertSee('data-minuti="45"', false);
    }

    public function test_il_conteggio_della_pausa_si_sceglie_a_scatti_di_15_minuti_in_scansione_oraria(): void
    {
        $this->scuola();   // mensa di 40'
        Slot::query()->where('ordine', 1)->update(['fine' => '08:50:00']);
        $docente = Docente::factory()->create();
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);
        $this->salva($referente, $docente, [['giorno' => 1, 'ordine' => 1]]);
        $assistenza = app(AssistenzaPause::class);
        $this->assertSame(0.75, $assistenza->ore($docente->fresh()));            // automatico: 40' -> 45'
        $this->assertSame(45, AssistenzaPause::conteggio(null, 40));
        $this->assertSame(60, AssistenzaPause::conteggio(null, 50));             // mensa da 50' = 1 ora

        $ore = [1 => ['inizio' => '08:00', 'fine' => '08:50', 'ricreazione' => 40, 'nome' => 'Mensa', 'conteggio' => 60], 2 => ['inizio' => '09:30', 'fine' => '10:20']];
        $this->actingAs($referente)->put('/scansione-oraria', ['ore' => $ore])->assertSessionHasNoErrors();
        $this->assertSame(1.0, $assistenza->ore($docente->fresh()));

        $ore[1]['conteggio'] = 50;   // non multiplo di 15
        $this->actingAs($referente)->put('/scansione-oraria', ['ore' => $ore])->assertSessionHasErrors('ore.1.conteggio');
    }

    public function test_la_pausa_si_collega_a_un_aula_di_tipo_pausa_che_compare_nei_pdf_e_nell_elenco_del_docente(): void
    {
        $this->scuola();
        $refettorio = \App\Models\Aula::factory()->create(['nome' => 'Refettorio', 'tipo' => 'pausa']);
        $palestra = \App\Models\Aula::factory()->create(['nome' => 'Palestra A', 'tipo' => 'palestra']);
        $docente = Docente::factory()->create();
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);
        $this->salva($referente, $docente, [['giorno' => 1, 'ordine' => 1]]);
        $ore = [1 => ['inizio' => '08:00', 'fine' => '08:50', 'ricreazione' => 40, 'nome' => 'Mensa', 'aula' => $refettorio->id], 2 => ['inizio' => '09:30', 'fine' => '10:20']];

        $this->actingAs($referente)->get('/scansione-oraria')->assertOk()->assertSee('Refettorio')->assertDontSee('Palestra A');
        $this->actingAs($referente)->put('/scansione-oraria', ['ore' => $ore])->assertSessionHasNoErrors();
        $this->assertSame($refettorio->id, Slot::query()->where('ordine', 1)->first()->ricreazione_aula_id);
        $this->assertSame(['Lun · Mensa 08:50–09:30 (Refettorio)'], app(AssistenzaPause::class)->elenco($docente->fresh()));

        // solo aule di tipo pausa; eliminando l'aula il collegamento si perde
        $ore[1]['aula'] = $palestra->id;
        $this->actingAs($referente)->put('/scansione-oraria', ['ore' => $ore])->assertSessionHasErrors('ore.1.aula');
        $refettorio->delete();
        $this->assertNull(Slot::query()->where('ordine', 1)->first()->ricreazione_aula_id);
    }
}
