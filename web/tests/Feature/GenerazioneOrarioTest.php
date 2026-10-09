<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test end-to-end della pipeline di generazione: pre-validazione,
 * ProblemBuilder, solver CP-SAT reale (nessun mock) e ResultImporter.
 * Richiede che solver/.venv sia installato (vedi CLAUDE.md).
 */
class GenerazioneOrarioTest extends TestCase
{
    use RefreshDatabase;

    private function scuolaMinima(): Classe
    {
        $sede = Sede::factory()->create();
        Aula::factory()->create(['sede_id' => $sede->id, 'tipo' => 'classe']);

        for ($ordine = 1; $ordine <= 2; $ordine++) {
            Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine]);
        }

        $quadro = QuadroOrario::factory()->create(['ore_totali' => 2]);
        $ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano']);
        $mat = Disciplina::factory()->create(['codice' => 'MAT', 'nome' => 'Matematica']);
        $quadro->righe()->create(['disciplina_id' => $ita->id, 'ore_settimanali' => 1]);
        $quadro->righe()->create(['disciplina_id' => $mat->id, 'ore_settimanali' => 1]);

        $classe = Classe::factory()->create(['sede_id' => $sede->id, 'quadro_orario_id' => $quadro->id]);
        $classe->slotAttivi()->sync(Slot::query()->pluck('id'));

        $docente = Docente::factory()->create();
        $classe->cattedre()->create(['docente_id' => $docente->id, 'disciplina_id' => $ita->id, 'ore' => 1]);
        $classe->cattedre()->create(['docente_id' => $docente->id, 'disciplina_id' => $mat->id, 'ore' => 1]);

        return $classe;
    }

    public function test_genera_un_orario_valido_per_una_scuola_minima(): void
    {
        $classe = $this->scuolaMinima();
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $response = $this->actingAs($referente)->post('/generazioni', [
            'time_limit_s' => 30,
            'seed' => 42,
        ]);

        $response->assertRedirect();

        $generazione = Generazione::query()->latest('id')->first();
        $this->assertSame('completata', $generazione->stato);
        $this->assertNotNull($generazione->orario_id);

        $lezioni = $generazione->orario->lezioni;
        $this->assertCount(2, $lezioni);
        $this->assertSame(
            $classe->slotAttivi->pluck('id')->sort()->values()->all(),
            $lezioni->pluck('slot_id')->sort()->values()->all(),
        );
    }

    public function test_la_docente_clil_e_presente_nelle_ore_indicate_e_il_marcatore_arriva_all_orario(): void
    {
        $classe = $this->scuolaMinima();
        $clil = Docente::factory()->create();
        $italiano = $classe->cattedre()->whereHas('disciplina', fn ($q) => $q->where('codice', 'ITA'))->first();
        $italiano->update(['docente_clil_id' => $clil->id, 'ore_clil' => 1]);

        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 10);
        $this->assertContains([$italiano->docente_id, $clil->id], array_column($problema['lezioni'], 'docenti'));

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->post('/generazioni', ['time_limit_s' => 30, 'seed' => 42]);

        $orario = Generazione::query()->latest('id')->first()->orario;
        $this->assertSame(1, $orario->lezioni()->where('con_clil', true)->count());
        $this->assertSame($italiano->id, $orario->lezioni()->where('con_clil', true)->first()->cattedra_id);
        $this->assertSame(1, \App\Models\Lezione::query()->where('orario_id', $orario->id)->delDocente($clil->id)->count());
    }

    public function test_l_orario_prende_il_nome_della_generazione_e_i_seed_gia_usati_sono_in_una_select(): void
    {
        $this->scuolaMinima();
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->actingAs($referente)->post('/generazioni', ['time_limit_s' => 30, 'seed' => 42, 'nome' => 'Orario di base']);
        $generazione = Generazione::query()->latest('id')->first();
        $this->assertSame('Orario di base', $generazione->orario->nome);

        $this->actingAs($referente)->get('/generazioni/create')->assertOk()
            ->assertSee('Casuale', false)
            ->assertSee('Come «Orario di base (seed 42)»', false)
            ->assertDontSee('type="number" name="seed"', false); // niente campo libero

        // senza seed (casuale) se ne sceglie uno nuovo
        $this->actingAs($referente)->post('/generazioni', ['time_limit_s' => 30, 'seed' => '']);
        $this->assertNotNull(Generazione::query()->latest('id')->first()->seed);
    }

    public function test_la_pre_validazione_blocca_un_quadro_orario_incoerente(): void
    {
        $classe = $this->scuolaMinima();
        // rompe la coerenza ore-quadro vs ore-cattedre.
        $classe->cattedre()->first()->update(['ore' => 5]);

        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->actingAs($referente)->post('/generazioni', ['time_limit_s' => 10]);

        $generazione = Generazione::query()->latest('id')->first();
        $this->assertSame('infattibile', $generazione->stato);
        $this->assertNotEmpty($generazione->diagnostica);
    }

    public function test_genera_un_orario_con_compresenze_di_sostegno(): void
    {
        $classe = $this->scuolaMinima();
        $docenteSostegno = Docente::factory()->create(['tipo_posto' => 'sostegno']);
        $classe->fabbisogniSostegno()->create(['codice_anonimo' => '1B-S1', 'ore_settimanali' => 2]);
        $classe->assegnazioniSostegno()->create(['docente_id' => $docenteSostegno->id, 'ore' => 2]);

        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->actingAs($referente)->post('/generazioni', ['time_limit_s' => 30, 'seed' => 7]);

        $generazione = Generazione::query()->latest('id')->first();
        $this->assertSame('completata', $generazione->stato);

        $compresenze = $generazione->orario->compresenzeSostegno;
        $this->assertCount(2, $compresenze);
        $this->assertTrue($compresenze->every(fn ($c) => $c->docente_id === $docenteSostegno->id && $c->codice_anonimo === '1B-S1'));
    }
}
