<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\QuadroOrario;
use App\Models\Sospensione;
use App\Models\User;
use App\Services\ConseguenzeEliminazione;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConseguenzeEliminazioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminare_un_docente_porta_via_cattedre_lezioni_e_sospensioni_e_scollega_le_utenze(): void
    {
        $docente = Docente::factory()->create();
        $cattedre = Cattedra::factory()->count(2)->create(['docente_id' => $docente->id]);
        $orario = Orario::factory()->create();
        foreach ([$cattedre[0], $cattedre[0], $cattedre[1]] as $c) {
            Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $c->id]);
        }
        Sospensione::query()->create(['docente_id' => $docente->id, 'dal' => now(), 'motivo' => 'malattia', 'esclude_da_orario' => true]);
        User::factory()->create(['ruolo' => 'docente', 'docente_id' => $docente->id]);

        $r = app(ConseguenzeEliminazione::class)->per('docenti', [$docente->id]);

        $this->assertContains('2 cattedre', $r['cascata']);
        $this->assertContains('3 lezioni degli orari', $r['cascata']);
        $this->assertContains('1 sospensione', $r['cascata']);
        $this->assertSame(['1 utenza'], $r['collegamenti']);   // l'utenza resta, senza il docente
    }

    public function test_un_quadro_orario_porta_via_le_classi_e_un_aula_scollega_soltanto(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $aula = Aula::factory()->create();
        Classe::factory()->count(3)->create(['quadro_orario_id' => $quadro->id, 'aula_base_id' => $aula->id]);

        $quadri = app(ConseguenzeEliminazione::class)->per('quadri_orari', [$quadro->id]);
        $this->assertContains('3 classi', $quadri['cascata']);

        $aule = app(ConseguenzeEliminazione::class)->per('aule', [$aula->id]);
        $this->assertSame([], $aule['cascata']);
        $this->assertContains('3 classi', $aule['collegamenti']);
    }

    public function test_senza_collegamenti_l_elenco_e_vuoto(): void
    {
        $docente = Docente::factory()->create();

        $this->assertSame(['cascata' => [], 'collegamenti' => []], app(ConseguenzeEliminazione::class)->per('docenti', [$docente->id]));
    }

    public function test_l_anteprima_e_un_endpoint_protetto_con_tabelle_ammesse(): void
    {
        $docente = Docente::factory()->create();
        Cattedra::factory()->create(['docente_id' => $docente->id]);

        $this->getJson('/elimina/conseguenze?tabella=docenti&ids[]=1')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
        $this->getJson('/elimina/conseguenze?tabella=docenti&ids[]='.$docente->id)->assertOk()->assertJsonPath('cascata.0', '1 cattedra');
        $this->getJson('/elimina/conseguenze?tabella=audit_log&ids[]=1')->assertStatus(422);        // solo le liste da cui si elimina
        $this->getJson('/elimina/conseguenze?tabella=docenti')->assertStatus(422);

        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->getJson('/elimina/conseguenze?tabella=docenti&ids[]=1')->assertForbidden();
    }

    public function test_le_barre_di_selezione_delle_liste_dichiarano_la_tabella(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));

        foreach (['docenti' => '/docenti', 'classi' => '/classi', 'quadri_orari' => '/quadri-orari', 'aule' => '/aule', 'sedi' => '/sedi', 'orari' => '/orari', 'users' => '/utenze'] as $tabella => $url) {
            $this->get($url)->assertOk()->assertSee('data-tabella="'.$tabella.'"', false);
        }
    }
}
