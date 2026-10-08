<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use App\Services\Editor\ControlloOrario;
use App\Services\Export\OrarioPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aule: visibili nelle griglie e nei PDF, vista per aula, aula ricalcolata a ogni spostamento, controlli sulle aule. */
class AuleOrarioTest extends TestCase
{
    use RefreshDatabase;

    private Orario $orario;

    private Slot $s1;

    private Slot $s2;

    private Sede $sede;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orario = Orario::factory()->create();
        $this->sede = Sede::query()->firstOrFail(); // la sede predefinita: quella in cui lavora l'utente nei test
        $this->s1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
        $this->s2 = Slot::factory()->create(['giorno' => 1, 'ordine' => 2, 'inizio' => '08:50:00', 'fine' => '09:40:00']);
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario', 'name' => 'Utente Prova']);
    }

    private function aula(string $nome, string $tipo = 'classe', int $capienza = 1): Aula
    {
        return Aula::factory()->create(['sede_id' => $this->sede->id, 'nome' => $nome, 'tipo' => $tipo, 'capienza' => $capienza]);
    }

    private function classe(string $anno, string $sezione, ?Aula $base = null): Classe
    {
        $classe = Classe::factory()->create(['anno_corso' => $anno, 'sezione' => $sezione, 'sede_id' => $this->sede->id, 'aula_base_id' => $base?->id]);
        $classe->slotAttivi()->sync([$this->s1->id, $this->s2->id]);

        return $classe;
    }

    private function cattedra(Classe $classe, Disciplina $disciplina, string $cognome = 'Rossi'): Cattedra
    {
        return Cattedra::factory()->create([
            'classe_id' => $classe->id, 'disciplina_id' => $disciplina->id, 'ore' => 1,
            'docente_id' => Docente::factory()->create(['cognome' => $cognome])->id,
        ]);
    }

    private function lezione(Cattedra $cattedra, Slot $slot, ?Aula $aula = null): Lezione
    {
        return Lezione::factory()->create(['orario_id' => $this->orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id, 'aula_id' => $aula?->id]);
    }

    public function test_l_aula_compare_nelle_griglie_solo_se_diversa_dall_aula_base(): void
    {
        $base = $this->aula('Aula 1A');
        $palestra = $this->aula('Palestra', 'palestra');
        $classe = $this->classe(1, 'A', $base);
        $italiano = $this->cattedra($classe, Disciplina::factory()->create(['nome' => 'Italiano']));
        $motoria = $this->cattedra($classe, Disciplina::factory()->create(['nome' => 'Motoria', 'tipo_aula_richiesto' => 'palestra']), 'Verdi');
        $this->lezione($italiano, $this->s1, null);        // aula della classe: non si mostra
        $this->lezione($motoria, $this->s2, $palestra);    // palestra: si mostra
        $referente = $this->actingAs($this->referente());

        $html = $referente->get("/orari/{$this->orario->id}/classe/{$classe->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Palestra', $html);
        $this->assertStringNotContainsString('Aula 1A</div>', $html);

        // l'aula base assegnata esplicitamente non è un'informazione utile: non si ripete
        Lezione::query()->where('cattedra_id', $italiano->id)->update(['aula_id' => $base->id]);
        $this->assertStringNotContainsString('Aula 1A</div>', $referente->get("/orari/{$this->orario->id}/classe/{$classe->id}")->getContent());
    }

    public function test_in_dada_l_aula_compare_sempre_nella_classe_nel_docente_e_nei_pdf(): void
    {
        $aula = $this->aula('Aula Italiano 1', 'dada_italiano');
        $classe = $this->classe(1, 'A', null);   // DADA: nessuna aula base
        $cattedra = $this->cattedra($classe, Disciplina::factory()->create(['nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_italiano']));
        $this->lezione($cattedra, $this->s1, $aula);
        $referente = $this->actingAs($this->referente());

        $referente->get("/orari/{$this->orario->id}/classe/{$classe->id}")->assertOk()->assertSee('Aula Italiano 1');
        $referente->get("/orari/{$this->orario->id}/docente/{$cattedra->docente_id}")->assertOk()->assertSee('Aula Italiano 1');

        $esporta = app(OrarioPdfExporter::class);
        $this->assertStringContainsString('Aula Italiano 1', $esporta->classe($this->orario, $classe)->getDomPDF()->outputHtml());
        $this->assertStringContainsString('Aula Italiano 1', $esporta->docente($this->orario, $cattedra->docente)->getDomPDF()->outputHtml());
    }

    public function test_la_vista_per_aula_mostra_chi_c_e_a_ogni_ora_comprese_le_classi_con_quell_aula_base(): void
    {
        $aula = $this->aula('Aula Italiano 1', 'dada_italiano');
        $base = $this->aula('Aula 2B');
        $x = $this->classe(1, 'A', null);
        $y = $this->classe(2, 'B', $base);
        $this->lezione($this->cattedra($x, Disciplina::factory()->create(['nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_italiano']), 'Rossi'), $this->s1, $aula);
        $this->lezione($this->cattedra($y, Disciplina::factory()->create(['nome' => 'Storia']), 'Bianchi'), $this->s2, null);   // aula base 2B
        $referente = $this->actingAs($this->referente());

        $referente->get("/orari/{$this->orario->id}/aula/{$aula->id}")->assertOk()
            ->assertSee('Orario aula Aula Italiano 1')->assertSee('1ª A', false)->assertSee('Italiano')->assertSee('Rossi')
            ->assertSee('1 ora occupata')->assertSee('libera')->assertDontSee('Bianchi');

        // l'aula base di una classe mostra le sue lezioni ordinarie
        $referente->get("/orari/{$this->orario->id}/aula/{$base->id}")->assertOk()->assertSee('Storia')->assertSee('Bianchi')->assertDontSee('Rossi');

        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get("/orari/{$this->orario->id}/aula/{$aula->id}")->assertForbidden();
    }

    public function test_la_vista_aula_evidenzia_le_ore_con_piu_classi_di_quante_l_aula_ne_ospiti(): void
    {
        $aula = $this->aula('Aula Italiano 1', 'dada_italiano');   // capienza 1
        $disciplina = Disciplina::factory()->create(['nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_italiano']);
        $this->lezione($this->cattedra($this->classe(1, 'A'), $disciplina), $this->s1, $aula);
        $this->lezione($this->cattedra($this->classe(1, 'B'), $disciplina), $this->s1, $aula);

        $this->actingAs($this->referente())->get("/orari/{$this->orario->id}/aula/{$aula->id}")->assertOk()
            ->assertSee('2 classi insieme, l\'aula ne ospita 1', false);
    }

    public function test_aula_con_capienza_due_mostra_due_classi_nella_stessa_ora(): void
    {
        $palestra = $this->aula('Palestra', 'palestra', 2);
        $disciplina = Disciplina::factory()->create(['nome' => 'Motoria', 'tipo_aula_richiesto' => 'palestra']);
        $x = $this->classe(1, 'A');
        $y = $this->classe(1, 'B');
        $this->lezione($this->cattedra($x, $disciplina, 'Neri'), $this->s1, $palestra);
        $this->lezione($this->cattedra($y, $disciplina, 'Gialli'), $this->s1, $palestra);

        $this->actingAs($this->referente())->get("/orari/{$this->orario->id}/aula/{$palestra->id}")->assertOk()->assertSee('Neri')->assertSee('Gialli')->assertSee('2 ore occupate');
        $html = app(OrarioPdfExporter::class)->aule($this->orario, collect([$palestra]))->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Neri', $html);
        $this->assertStringContainsString('Gialli', $html);
        $this->assertSame([], array_filter(app(ControlloOrario::class)->problemi($this->orario), fn ($p) => str_contains($p['testo'], 'insieme'))); // capienza 2: nessun conflitto
    }

    public function test_il_pdf_delle_aule_ha_un_foglio_per_aula_usata(): void
    {
        $uno = $this->aula('Aula Uno', 'dada_x');
        $due = $this->aula('Aula Due', 'dada_x');
        $this->aula('Aula Vuota', 'dada_x');
        $disciplina = Disciplina::factory()->create(['nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_x']);
        $this->lezione($this->cattedra($this->classe(1, 'A'), $disciplina), $this->s1, $uno);
        $this->lezione($this->cattedra($this->classe(1, 'B'), $disciplina), $this->s1, $due);

        $html = app(OrarioPdfExporter::class)->aule($this->orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Orario aula Aula Uno', $html);
        $this->assertStringContainsString('Orario aula Aula Due', $html);
        $this->assertStringNotContainsString('Aula Vuota', $html);                      // nessuna ora: nessun foglio
        $this->assertSame(1, substr_count($html, 'page-break-after: always'));          // 2 aule = 1 interruzione

        $referente = $this->actingAs($this->referente());
        foreach (["/orari/{$this->orario->id}/export/aule", "/orari/{$this->orario->id}/export/aula/{$uno->id}"] as $url) {
            $risposta = $referente->get($url)->assertOk();
            $this->assertSame('application/pdf', $risposta->headers->get('Content-Type'));
        }
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get("/orari/{$this->orario->id}/export/aule")->assertForbidden();
    }

    public function test_l_elenco_orari_offre_la_vista_per_aula_e_il_pdf_delle_aule(): void
    {
        $this->aula('Aula Italiano 1', 'dada_italiano');
        $this->actingAs($this->referente())->get('/orari')->assertOk()
            ->assertSee('Vista aula…')->assertSee('Aula Italiano 1')->assertSee('Tutte le aule')->assertSee('/orari/'.$this->orario->id.'/aula', false);
    }

    /** DADA: due aule dello stesso tipo, due classi senza aula base. */
    private function scenarioDada(): array
    {
        $a1 = $this->aula('Italiano 1', 'dada_ita');
        $a2 = $this->aula('Italiano 2', 'dada_ita');
        $x = $this->classe(1, 'A');
        $y = $this->classe(1, 'B');
        $ita = Disciplina::factory()->create(['nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_ita']);
        $sto = Disciplina::factory()->create(['nome' => 'Storia']);

        return [$a1, $a2, $x, $y, $ita, $sto];
    }

    public function test_spostando_una_lezione_l_aula_viene_ricalcolata_e_preferisce_quella_di_prima(): void
    {
        [$a1, $a2, $x, $y, $ita, $sto] = $this->scenarioDada();
        $lezX = $this->lezione($this->cattedra($x, $ita, 'Rossi'), $this->s1, $a1);
        $this->lezione($this->cattedra($x, $sto, 'Verdi'), $this->s2, null);
        $this->lezione($this->cattedra($y, $ita, 'Bianchi'), $this->s1, $a2);
        $referente = $this->actingAs($this->referente());
        $base = "/orari/{$this->orario->id}";

        // alla 2ª ora l'aula 1 è libera: la lezione ci resta (stessa aula, stabilità)
        $referente->patchJson("$base/lezioni/{$lezX->id}/sposta", ['slot_id' => $this->s2->id])->assertOk();
        $this->assertSame($a1->id, $lezX->fresh()->aula_id);

        // ora la 1ª ora è occupata nell'aula 1 da un'altra classe: tornando indietro serve l'altra aula
        $z = $this->classe(2, 'C');
        $this->lezione($this->cattedra($z, $ita, 'Gialli'), $this->s1, $a1);
        $referente->post("$base/annulla-ultima");
        $this->assertSame($this->s1->id, $lezX->fresh()->slot_id);
        // a1 è occupata da Gialli e a2 da Bianchi: nessuna aula libera, l'aula resta da assegnare e il Controllo lo dice
        $this->assertNull($lezX->fresh()->aula_id);
        $this->assertTrue(collect(app(ControlloOrario::class)->problemi($this->orario))->contains(fn ($p) => str_contains($p['testo'], 'non ne ha una assegnata') && str_contains($p['testo'], '1ª A')));
    }

    public function test_una_lezione_dada_cambia_aula_se_quella_di_prima_non_e_libera(): void
    {
        [$a1, $a2, $x, $y, $ita, $sto] = $this->scenarioDada();
        $lezX = $this->lezione($this->cattedra($x, $ita, 'Rossi'), $this->s1, $a1);
        $this->lezione($this->cattedra($x, $sto, 'Verdi'), $this->s2, null);
        $this->lezione($this->cattedra($y, $ita, 'Bianchi'), $this->s2, $a1);   // alla 2ª ora a1 è occupata da 1ªB

        $this->actingAs($this->referente())->patchJson("/orari/{$this->orario->id}/lezioni/{$lezX->id}/sposta", ['slot_id' => $this->s2->id])->assertOk();

        $this->assertSame($a2->id, $lezX->fresh()->aula_id);             // a1 occupata → a2
        $this->assertSame([], array_filter(app(ControlloOrario::class)->problemi($this->orario), fn ($p) => $p['gravita'] === 'errore' && str_contains($p['testo'], 'Italiano 1')));
    }

    public function test_lo_scambio_riassegna_l_aula_a_entrambe_le_lezioni(): void
    {
        [$a1, $a2, $x, $y, $ita, $sto] = $this->scenarioDada();
        $mat = Disciplina::factory()->create(['nome' => 'Matematica', 'tipo_aula_richiesto' => 'dada_ita']);
        $l1 = $this->lezione($this->cattedra($x, $ita, 'Rossi'), $this->s1, $a1);
        $l2 = $this->lezione($this->cattedra($x, $mat, 'Verdi'), $this->s2, $a1);
        $this->lezione($this->cattedra($y, $ita, 'Bianchi'), $this->s1, $a2);

        $this->actingAs($this->referente())->patchJson("/orari/{$this->orario->id}/lezioni/{$l1->id}/sposta", ['slot_id' => $this->s2->id])->assertOk();

        $this->assertSame([$this->s2->id, $this->s1->id], [$l1->fresh()->slot_id, $l2->fresh()->slot_id]);
        $this->assertSame([$a1->id, $a1->id], [$l1->fresh()->aula_id, $l2->fresh()->aula_id]);   // a1 libera in entrambi gli slot dopo lo scambio
        $this->assertSame([], array_filter(app(ControlloOrario::class)->problemi($this->orario), fn ($p) => $p['gravita'] === 'errore'));
    }

    public function test_il_controllo_segnala_aula_doppia_mancante_e_di_tipo_sbagliato(): void
    {
        [$a1, $a2, $x, $y, $ita, $sto] = $this->scenarioDada();
        $altra = $this->aula('Laboratorio', 'laboratorio');
        $lx = $this->lezione($this->cattedra($x, $ita, 'Rossi'), $this->s1, $a1);
        $ly = $this->lezione($this->cattedra($y, $ita, 'Bianchi'), $this->s1, $a1);   // stessa aula, capienza 1
        $testi = fn () => array_column(app(ControlloOrario::class)->problemi($this->orario), 'testo');

        $this->assertTrue(collect($testi())->contains(fn ($t) => str_contains($t, 'Italiano 1, lunedì, 1ª ora (08:00–08:50): usata da 2 classi insieme') && str_contains($t, '1ª A') && str_contains($t, '1ª B')));

        $ly->update(['aula_id' => null]);
        $this->assertTrue(collect($testi())->contains(fn ($t) => str_contains($t, '1ª B, lunedì, 1ª ora') && str_contains($t, "richiede un'aula di tipo 'dada_ita' ma non ne ha una assegnata")));

        $ly->update(['aula_id' => $altra->id]);
        $this->assertTrue(collect($testi())->contains(fn ($t) => str_contains($t, 'ma è in Laboratorio')));

        $ly->update(['aula_id' => $a2->id]);
        $this->assertSame([], array_filter($testi(), fn ($t) => str_contains($t, 'aula') || str_contains($t, 'Italiano 1')));
        $this->assertNotNull($lx);
    }
}
