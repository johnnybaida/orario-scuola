<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Sospensione;
use App\Models\User;
use App\Services\Validation\PreValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SostituzioniTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['ruolo' => 'amministratore']);
    }

    /** Titolare con due cattedre, sospensione in corso e due supplenti indicati. */
    private function scenario(): array
    {
        $titolare = Docente::factory()->create(['cognome' => 'Titolare']);
        [$a, $b] = [Docente::factory()->create(['cognome' => 'Alfa']), Docente::factory()->create(['cognome' => 'Beta'])];
        $c1 = Cattedra::factory()->create(['docente_id' => $titolare->id]);
        $c2 = Cattedra::factory()->create(['docente_id' => $titolare->id]);
        $s = Sospensione::query()->create(['docente_id' => $titolare->id, 'dal' => now()->subDay(), 'al' => null, 'motivo' => 'malattia', 'esclude_da_orario' => true]);
        $s->supplenti()->sync([$a->id, $b->id]);

        return [$titolare, $a, $b, $c1, $c2, $s];
    }

    public function test_i_supplenti_si_indicano_dalla_scheda_del_docente(): void
    {
        $titolare = Docente::factory()->create();
        $supplente = Docente::factory()->create();

        $dati = fn (array $supplenti, ?int $id = null) => [
            'nome' => $titolare->nome, 'cognome' => $titolare->cognome, 'tipo_contratto' => $titolare->tipo_contratto,
            'tipo_posto' => $titolare->tipo_posto, 'regime' => $titolare->regime, 'ore_dovute' => $titolare->ore_dovute,
            'sospensioni_inviate' => 1,
            'sospensioni' => [['id' => $id, 'dal' => '2026-10-01', 'al' => '', 'motivo' => 'malattia', 'esclude_da_orario' => '1', 'note' => '', 'supplenti' => $supplenti]],
        ];

        $this->actingAs($this->admin())->put("/docenti/{$titolare->id}", $dati([$supplente->id]))->assertSessionHasNoErrors();
        $s = Sospensione::query()->firstOrFail();
        $this->assertSame([$supplente->id], $s->supplenti->pluck('id')->all());

        // nella scheda i supplenti sono caselle da spuntare (non una select multipla), con quelli scelti già spuntati
        $this->get("/docenti/{$titolare->id}/edit")->assertOk()->assertSee('name="sospensioni[0][supplenti][]" value="'.$supplente->id.'" checked', false);

        // non può essere supplente di se stesso
        $this->put("/docenti/{$titolare->id}", $dati([$titolare->id], $s->id))->assertSessionHasErrors('sospensioni.0.supplenti.0');
        // senza supplenti inviati la selezione si svuota
        $this->put("/docenti/{$titolare->id}", $dati([], $s->id))->assertSessionHasNoErrors();
        $this->assertCount(0, $s->fresh()->supplenti);
    }

    public function test_passa_le_cattedre_ai_supplenti_scelti_e_le_lezioni_le_seguono(): void
    {
        [$titolare, $a, $b, $c1, $c2, $s] = $this->scenario();
        $lezione = Lezione::factory()->create(['cattedra_id' => $c1->id]);
        $this->actingAs($this->admin());

        // un supplente non indicato sulla sospensione è rifiutato
        $estraneo = Docente::factory()->create();
        $this->post("/sospensioni/{$s->id}/sostituzione", ['assegnazioni' => [$c1->id => $estraneo->id]])->assertSessionHasErrors('assegnazioni');
        $this->assertSame($titolare->id, $c1->fresh()->docente_id);

        // c1 ad Alfa, c2 resta al titolare (campo vuoto)
        $this->post("/sospensioni/{$s->id}/sostituzione", ['assegnazioni' => [$c1->id => $a->id, $c2->id => '']])->assertSessionHasNoErrors();
        $this->assertSame($a->id, $c1->fresh()->docente_id);
        $this->assertSame($s->id, $c1->fresh()->sospensione_id);
        $this->assertSame($titolare->id, $c2->fresh()->docente_id);
        $this->assertSame($c1->id, $lezione->fresh()->cattedra_id); // l'orario resta: cambia solo chi tiene la cattedra
        $this->assertDatabaseHas('audit_log', ['entita' => 'Cattedra', 'entita_id' => $c1->id, 'azione' => 'modifica']);

        $this->get("/sospensioni/{$s->id}/sostituzione")->assertOk()->assertSee('Alfa');
    }

    public function test_riporta_le_cattedre_al_titolare_e_togliere_la_sospensione_fa_lo_stesso(): void
    {
        [$titolare, $a, $b, $c1, $c2, $s] = $this->scenario();
        $this->actingAs($this->admin())->post("/sospensioni/{$s->id}/sostituzione", ['assegnazioni' => [$c1->id => $a->id, $c2->id => $b->id]]);

        $this->post("/sospensioni/{$s->id}/ripristina")->assertSessionHasNoErrors();
        $this->assertSame([$titolare->id, null], [$c1->fresh()->docente_id, $c1->fresh()->sospensione_id]);
        $this->assertSame($titolare->id, $c2->fresh()->docente_id);

        $this->post("/sospensioni/{$s->id}/sostituzione", ['assegnazioni' => [$c1->id => $a->id]]);
        $s->delete();
        $this->assertSame([$titolare->id, null], [$c1->fresh()->docente_id, $c1->fresh()->sospensione_id]);
    }

    public function test_la_prevalidazione_indirizza_alla_sostituzione_e_segnala_il_rientro(): void
    {
        [$titolare, $a, , $c1, $c2, $s] = $this->scenario();

        $problema = collect((new PreValidator)->problemi())->firstWhere(fn ($p) => str_contains($p['testo'], 'Titolare'));
        $this->assertStringContainsString('supplenti indicati', $problema['testo']);
        $this->assertSame(route('sostituzioni.form', $s), $problema['url']);

        $this->actingAs($this->admin())->post("/sospensioni/{$s->id}/sostituzione", ['assegnazioni' => [$c1->id => $a->id, $c2->id => $a->id]]);
        $this->assertNull(collect((new PreValidator)->problemi())->firstWhere(fn ($p) => str_contains($p['testo'], 'Titolare')));

        // sospensione finita ma cattedre ancora ai supplenti: da riportare
        $s->update(['al' => now()->subDays(2)]);
        $problema = collect((new PreValidator)->problemi())->firstWhere(fn ($p) => str_contains($p['testo'], 'riportale al titolare'));
        $this->assertSame(route('sostituzioni.form', $s), $problema['url']);
    }

    public function test_solo_chi_gestisce_l_anagrafica_passa_le_cattedre(): void
    {
        [, , , , , $s] = $this->scenario();

        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))->get("/sospensioni/{$s->id}/sostituzione")->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get("/sospensioni/{$s->id}/sostituzione")->assertOk();
    }
}
