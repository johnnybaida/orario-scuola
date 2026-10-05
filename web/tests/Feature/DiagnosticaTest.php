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
}
