<?php

namespace Tests\Feature;

use App\Models\AssegnazioneSostegno;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocenteOreTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_elenco_mostra_le_ore_assegnate_su_quelle_dovute_con_il_sostegno(): void
    {
        $docente = Docente::factory()->create(['cognome' => 'Rossi', 'ore_dovute' => 18]);
        Cattedra::factory()->create(['docente_id' => $docente->id, 'ore' => 6]);
        Cattedra::factory()->create(['docente_id' => $docente->id, 'ore' => 4]);
        AssegnazioneSostegno::factory()->create(['docente_id' => $docente->id, 'classe_id' => Classe::factory(), 'ore' => 3]);
        $completo = Docente::factory()->create(['cognome' => 'Bianchi', 'ore_dovute' => 18]);
        Cattedra::factory()->create(['docente_id' => $completo->id, 'ore' => 18]);
        Docente::factory()->create(['cognome' => 'Verdi', 'ore_dovute' => 9]);   // senza ore

        $r = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/docenti')->assertOk()
            ->assertSee('Assegnate / dovute')->assertDontSee('Ore dovute</th>', false);

        $r->assertSee('13 / 18')->assertSee('18 / 18')->assertSee('0 / 9');   // 6 + 4 di cattedra + 3 di sostegno
    }
}
