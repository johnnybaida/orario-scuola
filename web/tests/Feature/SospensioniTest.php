<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Docente;
use App\Models\Sospensione;
use App\Models\User;
use App\Services\Validation\PreValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SospensioniTest extends TestCase
{
    use RefreshDatabase;

    private function modifica(User $utente, Docente $docente, array $sospensioni)
    {
        return $this->actingAs($utente)->put("/docenti/{$docente->id}", [
            'nome' => $docente->nome, 'cognome' => $docente->cognome, 'tipo_contratto' => $docente->tipo_contratto,
            'tipo_posto' => $docente->tipo_posto, 'regime' => $docente->regime, 'ore_dovute' => $docente->ore_dovute,
            'sospensioni_inviate' => 1, 'sospensioni' => $sospensioni,
        ]);
    }

    private function problemiSospesi(): array
    {
        return array_values(array_filter((new PreValidator)->esegui(), fn ($t) => str_contains($t, 'riassegnale a un supplente')));
    }

    public function test_si_aggiungono_modificano_e_tolgono_sospensioni_dalla_scheda_del_docente(): void
    {
        $docente = Docente::factory()->create();
        $segreteria = User::factory()->create(['ruolo' => 'segreteria']);

        $this->modifica($segreteria, $docente, [['dal' => '2026-10-01', 'al' => '', 'motivo' => 'sospensione', 'esclude_da_orario' => '1', 'note' => '']])->assertSessionHasNoErrors();
        $s = Sospensione::query()->firstOrFail();
        $this->assertNull($s->al);                  // data di fine vuota = aperta
        $this->assertNull($s->note);
        $this->assertTrue($s->esclude_da_orario);

        $this->modifica($segreteria, $docente, [['id' => $s->id, 'dal' => '2026-10-01', 'al' => '2026-10-15', 'motivo' => 'malattia', 'note' => 'certificato']]);
        $s->refresh();
        $this->assertSame('2026-10-15', $s->al->format('Y-m-d'));
        $this->assertFalse($s->esclude_da_orario);  // casella non spuntata
        $this->assertDatabaseHas('audit_log', ['entita' => 'Sospensione', 'entita_id' => $s->id, 'azione' => 'modifica']);

        $this->modifica($segreteria, $docente, [])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('sospensioni', 0);
    }

    public function test_la_fine_non_puo_precedere_l_inizio_e_il_motivo_e_obbligatorio(): void
    {
        $docente = Docente::factory()->create();
        $utente = User::factory()->create(['ruolo' => 'referente_orario']);

        $this->modifica($utente, $docente, [['dal' => '2026-10-10', 'al' => '2026-10-01', 'motivo' => 'altro']])->assertSessionHasErrors('sospensioni.0.al');
        $this->modifica($utente, $docente, [['dal' => '2026-10-10', 'motivo' => 'inventato']])->assertSessionHasErrors('sospensioni.0.motivo');
        $this->assertDatabaseCount('sospensioni', 0);
    }

    public function test_la_prevalidazione_blocca_chi_e_sospeso_con_cattedre_solo_se_esclusa_dall_orario(): void
    {
        $docente = Docente::factory()->create(['cognome' => 'Sospesi']);
        Cattedra::factory()->create(['docente_id' => $docente->id]);
        $this->assertSame([], $this->problemiSospesi());

        // in corso ed esclusa: problema, con il link alla scheda
        $s = Sospensione::query()->create(['docente_id' => $docente->id, 'dal' => now()->subDay(), 'al' => null, 'motivo' => 'sospensione', 'esclude_da_orario' => true]);
        $this->assertCount(1, $this->problemiSospesi());
        $this->assertStringContainsString('Sospesi', $this->problemiSospesi()[0]);
        $this->assertSame(route('docenti.edit', $docente), collect((new PreValidator)->problemi())->firstWhere(fn ($p) => str_contains($p['testo'], 'supplente'))['url']);

        // assenza breve che non cambia l'orario base
        $s->update(['esclude_da_orario' => false]);
        $this->assertSame([], $this->problemiSospesi());

        // già finita o non ancora iniziata
        $s->update(['esclude_da_orario' => true, 'dal' => now()->subDays(10), 'al' => now()->subDays(2)]);
        $this->assertSame([], $this->problemiSospesi());
        $s->update(['dal' => now()->addDays(5), 'al' => null]);
        $this->assertSame([], $this->problemiSospesi());

        // sospeso ma senza cattedre: niente da riassegnare
        $s->update(['dal' => now()->subDay()]);
        $docente->cattedre()->delete();
        $this->assertSame([], $this->problemiSospesi());
    }

    public function test_l_elenco_docenti_segnala_chi_e_sospeso_oggi(): void
    {
        $attivo = Docente::factory()->create(['cognome' => 'Alfa']);
        $passato = Docente::factory()->create(['cognome' => 'Beta']);
        Sospensione::query()->create(['docente_id' => $attivo->id, 'dal' => now()->subDay(), 'al' => now()->addDay(), 'motivo' => 'congedo', 'esclude_da_orario' => true]);
        Sospensione::query()->create(['docente_id' => $passato->id, 'dal' => now()->subDays(9), 'al' => now()->subDays(3), 'motivo' => 'malattia', 'esclude_da_orario' => true]);

        $html = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/docenti')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '>Sospeso<'));
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get("/docenti/{$attivo->id}/edit")->assertOk()->assertSee('Sospensioni e assenze lunghe')->assertSee('Congedo o aspettativa');
    }

    public function test_il_ruolo_docente_non_puo_modificare_le_sospensioni(): void
    {
        $docente = Docente::factory()->create();
        $this->modifica(User::factory()->create(['ruolo' => 'docente']), $docente, [['dal' => '2026-10-01', 'motivo' => 'altro']])->assertForbidden();
        $this->assertDatabaseCount('sospensioni', 0);
    }
}
