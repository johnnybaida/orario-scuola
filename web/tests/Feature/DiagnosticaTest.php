<?php

namespace Tests\Feature;

use App\Models\Generazione;
use App\Models\Orario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticaTest extends TestCase
{
    use RefreshDatabase;

    private function generazioneFallita(): Generazione
    {
        $orario = Orario::factory()->create();

        return Generazione::query()->create([
            'periodo_id' => $orario->periodo_id, 'seed' => 42, 'time_limit_s' => 60, 'stato' => 'fallita',
            'diagnostica' => ['Errore tecnico del solver: TypeError in d1.py'],
        ]);
    }

    public function test_scarica_il_rapporto_con_errore_e_sezioni(): void
    {
        $g = $this->generazioneFallita();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))
            ->get(route('generazioni.diagnostica', $g))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="diagnostica-generazione-'.$g->id.'.txt"')
            ->assertSee('TypeError in d1.py')
            ->assertSee('=== VINCOLI ATTIVI ===')
            ->assertSee('=== PROBLEMA INVIATO AL SOLVER');
    }

    public function test_chi_non_gestisce_l_anagrafica_non_scarica(): void
    {
        $g = $this->generazioneFallita();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_sostituzioni']))
            ->get(route('generazioni.diagnostica', $g))->assertForbidden();
    }

    public function test_le_righe_di_conflitto_del_solver_diventano_vincoli_con_link_e_nomi_dei_docenti(): void
    {
        $docente = \App\Models\Docente::factory()->create(['cognome' => 'Brancato', 'nome' => 'Oriana']);
        $v1 = \App\Models\Vincolo::factory()->create(['tipo' => 'T4_ORE_GIORNO', 'ambito_livello' => 'docente', 'ambito_ids' => [$docente->id], 'parametri' => ['max_ore' => 5], 'severita' => 'rigido']);
        $v2 = \App\Models\Vincolo::factory()->create(['tipo' => 'D3_MAX_ORE_GIORNO', 'ambito_livello' => 'globale', 'ambito_ids' => [], 'parametri' => ['disciplina_ids' => [], 'max' => 2], 'severita' => 'rigido']);

        $righe = app(\App\Services\Solver\DiagnosticaSolver::class)->conLink([
            'Nessuna soluzione soddisfa i vincoli rigidi con i dati forniti.',
            "Conflitto tra vincoli: {$v1->id}, {$v2->id}, 999",
            "Vincolo {$v1->id} ristretto a docenti: {$docente->id}",
            'Anche senza i vincoli configurati l\'orario è impossibile: il problema è nei dati (cattedre).',
        ], []);
        $testi = array_column($righe, 'testo');

        $this->assertStringContainsString('Nessuna soluzione', $testi[0]);
        $this->assertStringContainsString('non possono valere tutti insieme', $testi[1]);
        $this->assertStringContainsString("Vincolo #{$v1->id} · Ore minime/massime al giorno (T4)", $testi[2]);
        $this->assertSame(route('vincoli.edit', $v1), $righe[2]['url']);
        $this->assertStringContainsString("Vincolo #{$v2->id}", $testi[3]);
        $this->assertStringContainsString('Vincolo #999 (non esiste più)', $testi[4]);
        $this->assertSame("Nel vincolo #{$v1->id} il conflitto riguarda: Brancato Oriana.", $testi[5]);
        $this->assertSame(route('docenti.edit', $docente), $righe[5]['url']);
        $this->assertStringContainsString('Anche senza i vincoli configurati', $testi[6]);
    }

    public function test_il_problema_per_il_solver_chiede_l_analisi_dell_infattibilita_e_il_runner_ne_aspetta_il_tempo(): void
    {
        $problema = app(\App\Services\Solver\ProblemBuilder::class)->costruisci(1, 60);

        $this->assertSame(\App\Services\Solver\ProblemBuilder::DIAGNOSI_S, $problema['diagnosi_s']);
        $this->assertGreaterThan(0, $problema['diagnosi_s']);
    }
}
