<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\QuadroOrarioRiga;
use App\Models\Slot;
use App\Models\User;
use App\Services\Solver\ProblemBuilder;
use App\Services\Validation\PreValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La mensa conta nel quadro orario e nel monte ore dei docenti ma non è una lezione da piazzare. */
class DisciplinaSenzaOraTest extends TestCase
{
    use RefreshDatabase;

    /** Classe con quadro 3h = 2h di italiano + 1h di mensa; 2 slot attivi. */
    private function scuola(): array
    {
        $quadro = QuadroOrario::factory()->create(['ore_totali' => 3]);
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        $mensa = Disciplina::factory()->create(['codice' => 'MEN', 'senza_slot' => true]);
        foreach ([[$ita, 2], [$mensa, 1]] as [$d, $ore]) {
            QuadroOrarioRiga::query()->create(['quadro_orario_id' => $quadro->id, 'disciplina_id' => $d->id, 'ore_settimanali' => $ore]);
        }
        $slot = collect([1, 2])->map(fn ($o) => Slot::factory()->create(['giorno' => 1, 'ordine' => $o]));
        $classe->slotAttivi()->sync($slot->pluck('id'));
        [$d1, $d2, $d3] = Docente::factory()->count(3)->create()->all();
        Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $ita->id, 'docente_id' => $d1->id, 'ore' => 2]);
        $prima = Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $mensa->id, 'docente_id' => $d2->id, 'ore' => 1]);
        $seconda = Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $mensa->id, 'docente_id' => $d3->id, 'ore' => 1, 'compresenza' => true]);

        return compact('classe', 'ita', 'mensa', 'prima', 'seconda', 'd2', 'd3');
    }

    private function erroriClasse(Classe $classe): array
    {
        return array_values(array_filter((new PreValidator)->esegui(), fn ($m) => str_contains($m, "Classe {$classe->nomeCompleto()}")));
    }

    public function test_la_mensa_con_due_docenti_conta_nel_quadro_e_non_blocca_la_generazione(): void
    {
        ['classe' => $classe, 'mensa' => $mensa] = $this->scuola();

        $this->assertSame([], $this->erroriClasse($classe));   // quadro 3 = 2 + 1 (la 2ª in compresenza non si somma); slot 2 = 3 - 1

        $problema = app(ProblemBuilder::class)->costruisci(1, 10);
        $this->assertCount(2, $problema['lezioni']);            // solo l'italiano
        $this->assertNotContains('MEN', array_column($problema['lezioni'], 'disciplina'));
    }

    public function test_se_la_mensa_non_e_segnata_senza_ora_gli_slot_non_tornano(): void
    {
        ['classe' => $classe, 'mensa' => $mensa] = $this->scuola();
        $mensa->update(['senza_slot' => false]);

        $this->assertNotEmpty($this->erroriClasse($classe));   // 2 slot attivi ma il quadro richiede 3h di lezione
    }

    public function test_le_ore_di_mensa_contano_nel_monte_ore_dei_docenti_e_la_spunta_si_salva(): void
    {
        ['d2' => $d2, 'd3' => $d3, 'mensa' => $mensa] = $this->scuola();
        $referente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));

        $referente->get('/docenti')->assertOk()->assertSee('1 / '.$d2->ore_dovute)->assertSee('1 / '.$d3->ore_dovute);

        $referente->put("/discipline/{$mensa->id}", ['codice' => 'MEN', 'nome' => $mensa->nome])->assertSessionHasNoErrors();
        $this->assertFalse($mensa->fresh()->senza_slot);                    // spunta tolta
        $referente->put("/discipline/{$mensa->id}", ['codice' => 'MEN', 'nome' => $mensa->nome, 'senza_slot' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($mensa->fresh()->senza_slot);
    }

    public function test_il_messaggio_e_la_scheda_della_classe_spiegano_quanti_slot_servono(): void
    {
        ['classe' => $classe, 'mensa' => $mensa] = $this->scuola();
        $classe->slotAttivi()->attach(Slot::factory()->create(['giorno' => 2, 'ordine' => 1])->id);   // 3 slot attivi invece di 2

        $messaggi = array_column((new PreValidator)->problemi(), 'testo');
        $this->assertTrue(collect($messaggi)->contains(fn ($m) => str_contains($m, 'ha 3 slot attivi ma ne servono 2: il quadro è di 3h e 1h sono di mensa')
            && str_contains($m, 'Aggiungi o togli ore negli «Slot attivi»')));

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get("/classi/{$classe->id}/edit")->assertOk()
            ->assertSee('Servono', false)->assertSee('<strong>2</strong> ore di lezione', false)->assertSee('1h senza ora, come la mensa', false);
    }

    public function test_i_campi_del_metodo_precedente_della_mensa_si_vedono_solo_nelle_discipline_che_li_usano(): void
    {
        $referente = $this->actingAs(\App\Models\User::factory()->create(['ruolo' => 'referente_orario']));
        $normale = Disciplina::factory()->create(['codice' => 'ITA']);
        $mensa = Disciplina::factory()->create(['codice' => 'MEN', 'senza_slot' => true]);

        $referente->get('/discipline/create')->assertOk()->assertDontSee('Non occupa un\'ora di lezione', false)->assertDontSee('Si svolge nella pausa');
        $referente->get("/discipline/{$normale->id}/edit")->assertOk()->assertDontSee('Non occupa un\'ora di lezione', false);
        $referente->get("/discipline/{$mensa->id}/edit")->assertOk()->assertSee('Non occupa un\'ora di lezione', false)->assertSee('Si svolge nella pausa');

        // salvare una disciplina normale col campo nascosto non cambia nulla
        $referente->put("/discipline/{$normale->id}", ['codice' => 'ITA', 'nome' => $normale->nome])->assertSessionHasNoErrors();
        $this->assertFalse($normale->fresh()->senza_slot);
    }
}
