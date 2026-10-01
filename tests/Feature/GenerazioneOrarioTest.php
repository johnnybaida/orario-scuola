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
