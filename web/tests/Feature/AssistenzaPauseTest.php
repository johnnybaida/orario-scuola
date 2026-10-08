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
}
