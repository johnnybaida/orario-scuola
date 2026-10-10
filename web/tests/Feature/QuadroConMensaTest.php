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

/** Le ore di mensa del quadro contano nel totale ma non sono lezioni da piazzare; la disciplina «senza ora» (vecchio metodo) non esiste più. */
class QuadroConMensaTest extends TestCase
{
    use RefreshDatabase;

    /** Classe con quadro 3h = 2h di italiano + 1h di mensa; 2 slot attivi. */
    private function scuola(): array
    {
        $quadro = QuadroOrario::factory()->create(['ore_totali' => 3, 'ore_mensa' => 1]);
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        QuadroOrarioRiga::query()->create(['quadro_orario_id' => $quadro->id, 'disciplina_id' => $ita->id, 'ore_settimanali' => 2]);
        $slot = collect([1, 2])->map(fn ($o) => Slot::factory()->create(['giorno' => 1, 'ordine' => $o]));
        $classe->slotAttivi()->sync($slot->pluck('id'));
        Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $ita->id, 'docente_id' => Docente::factory()->create()->id, 'ore' => 2]);

        return compact('classe');
    }

    private function erroriClasse(Classe $classe): array
    {
        return array_values(array_filter((new PreValidator)->esegui(), fn ($m) => str_contains($m, "Classe {$classe->nomeCompleto()}")));
    }

    public function test_le_ore_di_mensa_del_quadro_non_sono_lezioni_e_non_bloccano_la_generazione(): void
    {
        ['classe' => $classe] = $this->scuola();

        $this->assertSame([], $this->erroriClasse($classe));   // cattedre 2 = quadro 3 - mensa 1; slot attivi 2
        $this->assertCount(2, app(ProblemBuilder::class)->costruisci(1, 10)['lezioni']);
    }

    public function test_il_messaggio_e_la_scheda_della_classe_spiegano_quanti_slot_servono(): void
    {
        ['classe' => $classe] = $this->scuola();
        $classe->slotAttivi()->attach(Slot::factory()->create(['giorno' => 2, 'ordine' => 1])->id);   // 3 slot attivi invece di 2

        $messaggi = array_column((new PreValidator)->problemi(), 'testo');
        $this->assertTrue(collect($messaggi)->contains(fn ($m) => str_contains($m, 'ha 3 slot attivi ma ne servono 2: il quadro è di 3h e 1h sono di mensa')
            && str_contains($m, 'Aggiungi o togli ore negli «Slot attivi»')));

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get("/classi/{$classe->id}/edit")->assertOk()
            ->assertSee('Servono', false)->assertSee('<strong>2</strong> ore di lezione', false)->assertSee('1h di mensa', false);
    }

    public function test_la_disciplina_non_ha_piu_i_campi_senza_ora_e_pausa_e_ignora_quelli_inviati(): void
    {
        $referente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
        $normale = Disciplina::factory()->create(['codice' => 'ITA']);

        $referente->get('/discipline/create')->assertOk()->assertDontSee('Non occupa un\'ora di lezione', false)->assertDontSee('Si svolge nella pausa');
        $referente->get("/discipline/{$normale->id}/edit")->assertOk()->assertDontSee('Non occupa un\'ora di lezione', false);
        // un vecchio modulo che le invia ancora non provoca errori
        $referente->put("/discipline/{$normale->id}", ['codice' => 'ITA', 'nome' => $normale->nome, 'senza_slot' => 1, 'pausa_dopo_ora' => 1])->assertSessionHasNoErrors();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('discipline', 'senza_slot'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('discipline', 'pausa_dopo_ora'));
    }
}
