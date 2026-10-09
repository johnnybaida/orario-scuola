<?php

namespace Tests\Feature;

use App\Enums\TipoAula;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoAulaTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_enum_da_un_nome_leggibile_ai_tipi_noti_e_a_quelli_liberi(): void
    {
        $this->assertSame('dada_sec_ling', TipoAula::DadaSecondaLingua->value);
        $this->assertSame('DADA · Seconda lingua', TipoAula::etichettaDi('dada_sec_ling'));
        $this->assertSame('Palestra', TipoAula::etichettaDi('palestra'));
        $this->assertSame('DADA · Ita', TipoAula::etichettaDi('dada_ita'));        // DADA non previsto: ripulito
        $this->assertSame('Aula speciale', TipoAula::etichettaDi('aula_speciale')); // tipo inventato dalla scuola
        $this->assertTrue(TipoAula::eDada('dada_x') && ! TipoAula::eDada('palestra'));
        $this->assertSame(['classe', 'laboratorio', 'palestra', 'aula_musica', 'aula_sostegno', 'aula_alternativa', 'pausa'], TipoAula::comuni());
    }

    public function test_le_seconde_lingue_condividono_il_tipo_dada_sec_ling_le_altre_materie_usano_il_codice(): void
    {
        $francese = Disciplina::factory()->create(['codice' => 'FRA', 'nome' => 'Francese (seconda lingua)']);
        $spagnolo = Disciplina::factory()->create(['codice' => 'SPA', 'nome' => 'Spagnolo']);
        $tedesco = Disciplina::factory()->create(['codice' => 'XYZ', 'nome' => 'Tedesco (seconda lingua)']);   // riconosciuta dal nome
        $italiano = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano']);

        foreach ([$francese, $spagnolo, $tedesco] as $d) {
            $this->assertSame('dada_sec_ling', TipoAula::dadaPer($d));
        }
        $this->assertSame('dada_ita', TipoAula::dadaPer($italiano));
    }

    public function test_creando_un_aula_dada_per_il_francese_il_tipo_e_dada_sec_ling_e_la_disciplina_viene_collegata(): void
    {
        $sede = Sede::factory()->create();
        $francese = Disciplina::factory()->create(['codice' => 'FRA', 'nome' => 'Francese (seconda lingua)']);
        $spagnolo = Disciplina::factory()->create(['codice' => 'SPA', 'nome' => 'Spagnolo']);
        $referente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));

        $referente->post('/aule', ['sede_id' => $sede->id, 'nome' => 'Lingue 1', 'tipo' => 'dada', 'dada_discipline' => [$francese->id], 'capienza' => 1])->assertSessionHasNoErrors();
        $referente->post('/aule', ['sede_id' => $sede->id, 'nome' => 'Lingue 2', 'tipo' => 'dada', 'dada_discipline' => [$spagnolo->id], 'capienza' => 1]);

        $this->assertSame(2, Aula::query()->where('tipo', 'dada_sec_ling')->count());
        $this->assertSame('dada_sec_ling', $francese->fresh()->tipo_aula_richiesto);
        $this->assertSame('dada_sec_ling', $spagnolo->fresh()->tipo_aula_richiesto);
        $this->assertDatabaseMissing('aule', ['tipo' => 'dada_fra']);

        $referente->get('/aule')->assertOk()->assertSee('DADA · Seconda lingua');
    }

    public function test_la_migrazione_rinomina_dada_fra_in_dada_sec_ling_ed_e_reversibile(): void
    {
        $sede = Sede::factory()->create();
        Aula::factory()->create(['sede_id' => $sede->id, 'tipo' => 'dada_fra']);
        $disciplina = Disciplina::factory()->create(['tipo_aula_richiesto' => 'dada_fra']);
        $altra = Aula::factory()->create(['sede_id' => $sede->id, 'tipo' => 'dada_ita']);
        $migrazione = require database_path('migrations/2026_10_04_160000_rename_dada_fra_to_dada_sec_ling.php');

        $migrazione->up();
        $this->assertDatabaseHas('aule', ['tipo' => 'dada_sec_ling']);
        $this->assertSame('dada_sec_ling', $disciplina->fresh()->tipo_aula_richiesto);
        $this->assertSame('dada_ita', $altra->fresh()->tipo);          // gli altri non cambiano

        $migrazione->down();
        $this->assertDatabaseHas('aule', ['tipo' => 'dada_fra']);
        $this->assertSame('dada_fra', $disciplina->fresh()->tipo_aula_richiesto);
    }
}
