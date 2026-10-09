<?php

namespace Tests\Feature;

use App\Enums\TipoAula;
use App\Models\Aula;
use App\Models\Disciplina;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AulaDadaGruppoTest extends TestCase
{
    use RefreshDatabase;

    private function utente()
    {
        return $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
    }

    public function test_un_aula_dada_puo_servire_piu_discipline_con_un_tipo_condiviso(): void
    {
        $ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano']);
        $ing = Disciplina::factory()->create(['codice' => 'ING', 'nome' => 'Inglese']);

        $this->utente()->post('/aule', ['nome' => 'Lab lingue', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 1])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('aule', ['nome' => 'Lab lingue', 'tipo' => 'dada_ing__ita']);   // codici in ordine
        $this->assertSame('dada_ing__ita', $ita->fresh()->tipo_aula_richiesto);
        $this->assertSame('dada_ing__ita', $ing->fresh()->tipo_aula_richiesto);
        $this->assertSame('DADA · Ing + Ita', TipoAula::etichettaDi('dada_ing__ita'));
    }

    public function test_una_sola_disciplina_resta_come_prima_e_le_seconde_lingue_restano_nel_tipo_comune(): void
    {
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        $fra = Disciplina::factory()->create(['codice' => 'FRA', 'nome' => 'Francese']);
        $spa = Disciplina::factory()->create(['codice' => 'SPA', 'nome' => 'Spagnolo']);

        $this->assertSame('dada_ita', TipoAula::dadaPerGruppo(collect([$ita])));
        $this->assertSame('dada_sec_ling', TipoAula::dadaPerGruppo(collect([$fra, $spa])));
        $this->assertSame('dada_ita__sec_ling', TipoAula::dadaPerGruppo(collect([$ita, $fra])));
        $this->assertLessThanOrEqual(50, strlen(TipoAula::dadaPerGruppo(Disciplina::factory()->count(8)->create(['nome' => 'x'])->each(fn ($d) => $d->update(['codice' => 'LUNGA'.$d->id])))));
    }

    public function test_riaprendo_l_aula_le_discipline_sono_spuntate_e_salvare_non_cambia_il_tipo(): void
    {
        $ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano']);
        $ing = Disciplina::factory()->create(['codice' => 'ING', 'nome' => 'Inglese']);
        $utente = $this->utente();
        $utente->post('/aule', ['nome' => 'Lab lingue', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 1]);
        $aula = Aula::query()->where('nome', 'Lab lingue')->firstOrFail();

        $pagina = $utente->get("/aule/{$aula->id}/edit")->assertOk();
        foreach ([$ita, $ing] as $d) {
            $pagina->assertSee('name="dada_discipline[]" value="'.$d->id.'" checked', false);
        }
        $pagina->assertSee('<option value="dada" selected', false);

        // il caso che prima rompeva tutto: cambiare solo la capienza
        $utente->put("/aule/{$aula->id}", ['nome' => 'Lab lingue', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 2])->assertSessionHasNoErrors();
        $this->assertSame('dada_ing__ita', $aula->fresh()->tipo);
        $this->assertSame('dada_ing__ita', $ita->fresh()->tipo_aula_richiesto);
    }

    public function test_togliendo_una_disciplina_perde_il_collegamento_se_nessun_altra_aula_ha_il_tipo(): void
    {
        $ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano']);
        $ing = Disciplina::factory()->create(['codice' => 'ING', 'nome' => 'Inglese']);
        $utente = $this->utente();
        $utente->post('/aule', ['nome' => 'Lab lingue', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 1]);
        $aula = Aula::query()->firstOrFail();

        $utente->put("/aule/{$aula->id}", ['nome' => 'Lab lingue', 'tipo' => 'dada', 'dada_discipline' => [$ita->id], 'capienza' => 1])->assertSessionHasNoErrors();

        $this->assertSame('dada_ita', $aula->fresh()->tipo);
        $this->assertSame(['dada_ita'], $ita->fresh()->tipiAmmessi());
        $this->assertSame([], $ing->fresh()->tipiAmmessi());   // non richiede più un'aula che nessuno ha
    }

    public function test_togliendo_una_disciplina_la_conserva_se_un_altra_aula_ha_ancora_il_tipo(): void
    {
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        $ing = Disciplina::factory()->create(['codice' => 'ING']);
        $utente = $this->utente();
        $utente->post('/aule', ['nome' => 'Lab 1', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 1]);
        $utente->post('/aule', ['nome' => 'Lab 2', 'tipo' => 'dada', 'dada_discipline' => [$ita->id, $ing->id], 'capienza' => 1]);
        $lab2 = Aula::query()->where('nome', 'Lab 2')->firstOrFail();

        $utente->put("/aule/{$lab2->id}", ['nome' => 'Lab 2', 'tipo' => 'palestra', 'capienza' => 1])->assertSessionHasNoErrors();

        $this->assertSame('palestra', $lab2->fresh()->tipo);
        $this->assertSame(['dada_ing__ita'], $ing->fresh()->tipiAmmessi());   // Lab 1 serve ancora Italiano e Inglese
    }

    public function test_un_aula_dada_richiede_almeno_una_disciplina_e_solo_quelle_della_sede(): void
    {
        Sede::factory()->create();   // la sede di lavoro è la prima
        $altra = Sede::factory()->create();
        $estranea = Disciplina::factory()->create(['sede_id' => $altra->id, 'codice' => 'XXX']);
        $utente = $this->utente();

        $utente->post('/aule', ['nome' => 'A', 'tipo' => 'dada', 'capienza' => 1])->assertSessionHasErrors('dada_discipline');
        $utente->post('/aule', ['nome' => 'A', 'tipo' => 'dada', 'dada_discipline' => [$estranea->id], 'capienza' => 1])->assertSessionHasErrors('dada_discipline.0');
        $utente->post('/aule', ['nome' => 'B', 'tipo' => 'palestra', 'capienza' => 1])->assertSessionHasNoErrors();   // senza DADA le discipline non servono
    }

    public function test_ogni_disciplina_ha_la_sua_aula_e_tutte_ne_condividono_una(): void
    {
        $d = collect(['ITA', 'ING', 'SPA'])->map(fn ($c) => Disciplina::factory()->create(['codice' => $c, 'nome' => $c]));
        $utente = $this->utente();
        foreach ($d as $disc) {
            $utente->post('/aule', ['nome' => 'Aula '.$disc->codice, 'tipo' => 'dada', 'dada_discipline' => [$disc->id], 'capienza' => 1]);
        }
        $utente->post('/aule', ['nome' => 'Condivisa', 'tipo' => 'dada', 'dada_discipline' => $d->pluck('id')->all(), 'capienza' => 1])->assertSessionHasNoErrors();

        $ita = $d[0]->fresh();
        $this->assertEqualsCanonicalizing(['dada_ita', 'dada_ing__ita__sec_ling'], $ita->tipiAmmessi());
        $this->assertTrue($ita->accettaTipo('dada_ing__ita__sec_ling'));
        $utente->get('/aule')->assertSee('ING, ITA, SPA');
        // la disciplina mostra tutte le aule ammesse e salvarla non perde i tipi
        $utente->put("/discipline/{$ita->id}", ['codice' => 'ITA', 'nome' => 'ITA', 'tipo_aula_richiesto' => 'dada_ita', 'tipi_aula_extra' => ['dada_ing__ita__sec_ling']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['dada_ita', 'dada_ing__ita__sec_ling'], $ita->fresh()->tipiAmmessi());
    }
}
