<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\QuadroOrarioRiga;
use App\Models\User;
use App\Services\StatoCattedre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatoCattedreTest extends TestCase
{
    use RefreshDatabase;

    public function test_confronta_le_ore_delle_cattedre_con_il_quadro_orario(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        [$ita, $mat, $art, $fuori] = Disciplina::factory()->count(4)->create()->all();
        foreach ([[$ita, 6], [$mat, 4], [$art, 2]] as [$d, $ore]) {
            QuadroOrarioRiga::query()->create(['quadro_orario_id' => $quadro->id, 'disciplina_id' => $d->id, 'ore_settimanali' => $ore]);
        }
        $nuova = fn (Disciplina $d, int $ore, bool $compresenza = false) => Cattedra::factory()->create([
            'classe_id' => $classe->id, 'disciplina_id' => $d->id, 'ore' => $ore, 'compresenza' => $compresenza,
        ]);

        $ok = $nuova($ita, 4);                    // 4 + 2 = 6: giusto
        $ok2 = $nuova($ita, 2);
        $poche = $nuova($mat, 3);                 // 3 su 4
        $troppe = $nuova($art, 3);                // 3 su 2
        $nonPrevista = $nuova($fuori, 1);
        $compresenza = $nuova($ita, 2, true);     // non conta: ita resta a 6

        $stati = (new StatoCattedre)->per(Cattedra::with('classe', 'disciplina')->get());

        $this->assertSame('OK', $stati[$ok->id]['etichetta']);
        $this->assertSame('OK', $stati[$ok2->id]['etichetta']);
        $this->assertSame('Mancano 1 h', $stati[$poche->id]['etichetta']);
        $this->assertSame('1 h in più', $stati[$troppe->id]['etichetta']);
        $this->assertSame('Non nel quadro', $stati[$nonPrevista->id]['etichetta']);
        $this->assertSame('Compresenza', $stati[$compresenza->id]['etichetta']);
    }

    public function test_l_elenco_mostra_la_colonna_stato(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        $disciplina = Disciplina::factory()->create();
        QuadroOrarioRiga::query()->create(['quadro_orario_id' => $quadro->id, 'disciplina_id' => $disciplina->id, 'ore_settimanali' => 5]);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $disciplina->id, 'ore' => 3, 'docente_id' => Docente::factory()]);

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/cattedre')
            ->assertOk()->assertSee('Stato')->assertSee('Mancano 2 h');
    }
}
