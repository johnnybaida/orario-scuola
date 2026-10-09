<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cattedra;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\User;
use App\Services\DatiScuola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DatiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['ruolo' => 'amministratore']);
    }

    private function esporta(array $tabelle): string
    {
        return $this->post('/dati/esporta', ['tabelle' => $tabelle])->assertOk()->baseResponse->getFile()->getPathname();
    }

    private function carica(string $zip)
    {
        return $this->post('/dati/importa/controlla', ['file' => new UploadedFile($zip, 'dati.zip', 'application/zip', null, true)]);
    }

    public function test_esporta_tutto_e_reimporta_ripristinando_i_dati(): void
    {
        $this->actingAs($this->admin());
        $cattedra = Cattedra::factory()->create();
        $tabelle = (new DatiScuola)->tabelle();
        $this->assertNotContains('audit_log', $tabelle);
        $this->assertNotContains('sessions', $tabelle);
        $this->assertNotContains('users', $tabelle);
        $this->assertLessThan(array_search('cattedre', $tabelle), array_search('docenti', $tabelle)); // i padri prima dei figli

        $zip = $this->esporta($tabelle);

        $cattedra->docente->delete(); // cascata: via anche la cattedra
        $this->assertDatabaseCount('cattedre', 0);

        $this->carica($zip)->assertOk()->assertSee('Importa dati')->assertSee('Cattedre');
        $this->post('/dati/importa', ['tabelle' => $tabelle, 'conferma' => 1])->assertRedirect(route('dati.index'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('cattedre', ['id' => $cattedra->id, 'docente_id' => $cattedra->docente_id]);
        $this->assertDatabaseHas('docenti', ['id' => $cattedra->docente_id]);
        $this->assertTrue(AuditLog::query()->where('entita', 'Dati')->where('azione', 'importazione')->exists());
    }

    public function test_l_esportazione_globale_contiene_ogni_tabella_tranne_quelle_escluse_e_ripristina_le_colonne_nuove(): void
    {
        $this->actingAs($this->admin());
        // Escluse per scelta: registro, account, sessioni, code, cache, migrazioni.
        $escluse = ['audit_log', 'users', 'sessions', 'password_reset_tokens', 'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        $tutte = collect(\Illuminate\Support\Facades\Schema::getTableListing())->map(fn ($t) => preg_replace('/^.*\./', '', $t))->diff($escluse)->sort()->values()->all();
        $tabelle = (new DatiScuola)->tabelle();
        $this->assertSame([], array_values(array_diff($tutte, $tabelle)), 'tabelle dei dati non esportate');

        // colonne nuove: docente CLIL, lezione CLIL, disciplina senza ora, aule ammesse, aula della pausa
        $clil = Docente::factory()->create();
        $cattedra = Cattedra::factory()->create(['docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $cattedra->disciplina->update(['senza_slot' => true, 'tipi_aula_extra' => ['dada_ita']]);
        $aula = \App\Models\Aula::factory()->create(['tipo' => 'pausa']);
        $slot = \App\Models\Slot::factory()->create(['ricreazione_minuti' => 40, 'ricreazione_conteggio' => 60, 'ricreazione_aula_id' => $aula->id]);
        $lezione = \App\Models\Lezione::factory()->create(['cattedra_id' => $cattedra->id, 'slot_id' => $slot->id, 'con_clil' => true]);

        $zip = $this->esporta($tabelle);
        $cattedra->docente->delete();
        $clil->delete();
        $aula->delete();
        $this->carica($zip)->assertOk();
        $this->post('/dati/importa', ['tabelle' => $tabelle, 'conferma' => 1])->assertRedirect(route('dati.index'));

        $this->assertSame($clil->id, $cattedra->fresh()->docente_clil_id);
        $this->assertSame(1, $cattedra->fresh()->ore_clil);
        $this->assertTrue($lezione->fresh()->con_clil);
        $this->assertTrue($cattedra->disciplina->fresh()->senza_slot);
        $this->assertSame(['dada_ita'], $cattedra->disciplina->fresh()->tipi_aula_extra);
        $this->assertSame([$aula->id, 60], [$slot->fresh()->ricreazione_aula_id, $slot->fresh()->ricreazione_conteggio]);
    }

    public function test_non_importa_se_i_riferimenti_non_tornano_e_non_cambia_nulla(): void
    {
        $this->actingAs($this->admin());
        $cattedra = Cattedra::factory()->create();
        $zip = $this->esporta(['cattedre']);   // solo le cattedre, senza i docenti a cui si riferiscono
        $cattedra->docente->delete();
        Docente::factory()->create(['cognome' => 'Rimasto']);

        $this->carica($zip)->assertOk();
        $this->post('/dati/importa', ['tabelle' => ['cattedre'], 'conferma' => 1])->assertSessionHasErrors('dati');

        $this->assertDatabaseCount('cattedre', 0);
        $this->assertDatabaseHas('docenti', ['cognome' => 'Rimasto']);
    }

    public function test_le_utenze_non_si_esportano_e_i_riferimenti_agli_utenti_si_svuotano(): void
    {
        $this->actingAs($this->admin());
        $docente = Docente::factory()->create();
        $autore = User::factory()->create(['ruolo' => 'referente_orario']);
        $orario = \App\Models\Orario::factory()->create(['creato_da' => $autore->id]);

        $zip = $this->esporta((new DatiScuola)->tabelle());
        $z = new \ZipArchive;
        $z->open($zip);
        $this->assertFalse($z->locateName('users.json'));
        $this->assertNotFalse($z->locateName('orari.json'));

        // l'autore dell'orario non esiste più in questa installazione; un'utenza punta a un docente che l'archivio non ha
        $autore->delete();
        $nuovo = Docente::factory()->create();
        $collegata = User::factory()->create(['ruolo' => 'docente', 'docente_id' => $nuovo->id]);

        $this->carica($zip)->assertOk();
        $this->post('/dati/importa', ['tabelle' => (new DatiScuola)->tabelle(), 'conferma' => 1])->assertSessionHasNoErrors()->assertRedirect(route('dati.index'));

        $this->assertDatabaseHas('orari', ['id' => $orario->id, 'creato_da' => null]);
        $this->assertDatabaseMissing('docenti', ['id' => $nuovo->id]);
        $this->assertNull($collegata->fresh()->docente_id);
        $this->assertDatabaseHas('users', ['id' => $collegata->id]); // le utenze non si toccano
    }

    public function test_rifiuta_un_file_non_valido_o_una_generazione_in_corso(): void
    {
        $this->actingAs($this->admin());
        $zip = $this->esporta(['sedi']);

        // file che non è un archivio dell'app
        $finto = tempnam(sys_get_temp_dir(), 'x').'.zip';
        file_put_contents($finto, 'non uno zip');
        $this->carica($finto)->assertSessionHasErrors();

        // generazione in corso
        $this->carica($zip)->assertOk();
        Generazione::query()->create(['periodo_id' => \App\Models\Orario::factory()->create()->periodo_id, 'seed' => 1, 'time_limit_s' => 60, 'stato' => 'in_corso']);
        $this->post('/dati/importa', ['tabelle' => ['sedi'], 'conferma' => 1])->assertSessionHasErrors('dati');
    }

    public function test_solo_l_amministratore_e_serve_la_conferma(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/dati')->assertForbidden();
        $this->actingAs($this->admin())->get('/dati')->assertOk()->assertSee('Cattedre')->assertDontSee('Audit Log');

        $zip = $this->esporta(['sedi']);
        $this->carica($zip)->assertOk();
        $this->post('/dati/importa', ['tabelle' => ['sedi']])->assertSessionHasErrors('conferma');
    }
}
