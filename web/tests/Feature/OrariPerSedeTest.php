<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\Orario;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use App\Services\DatiScuola;
use App\Services\Export\OrarioPdfExporter;
use App\Services\SedeCorrente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/** Un orario per sede: generazioni, versioni, pubblicazione, PDF; gira il solver reale (serve solver/.venv). */
class OrariPerSedeTest extends TestCase
{
    use RefreshDatabase;

    private Sede $a;

    private Sede $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Sede::factory()->create(['nome' => 'Centrale']);
        $this->b = Sede::factory()->create(['nome' => 'Succursale']);
        $this->scuolaMinima($this->a, 'Alfa');
        $this->scuolaMinima($this->b, 'Beta');
        app(SedeCorrente::class)->imposta(null);
    }

    /** Una classe con due discipline in due ore, nella sede indicata. */
    private function scuolaMinima(Sede $sede, string $cognomeDocente): void
    {
        app(SedeCorrente::class)->imposta($sede->id);
        foreach ([1, 2] as $ordine) {
            Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine]);
        }
        $quadro = QuadroOrario::factory()->create(['ore_totali' => 2]);
        $ita = Disciplina::factory()->create(['codice' => 'ITA']);
        $mat = Disciplina::factory()->create(['codice' => 'MAT']);
        $quadro->righe()->create(['disciplina_id' => $ita->id, 'ore_settimanali' => 1]);
        $quadro->righe()->create(['disciplina_id' => $mat->id, 'ore_settimanali' => 1]);
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        $classe->slotAttivi()->sync(Slot::query()->pluck('id'));
        $docente = Docente::factory()->create(['cognome' => $cognomeDocente]);
        $classe->cattedre()->create(['docente_id' => $docente->id, 'disciplina_id' => $ita->id, 'ore' => 1]);
        $classe->cattedre()->create(['docente_id' => $docente->id, 'disciplina_id' => $mat->id, 'ore' => 1]);
    }

    private function genera($utente, Sede $sede)
    {
        $utente->post('/sede', ['sede_id' => $sede->id]);

        return $utente->post('/generazioni', ['time_limit_s' => 30, 'seed' => 7])->assertRedirect();
    }

    public function test_ogni_sede_ha_i_suoi_orari_con_versioni_indipendenti(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));

        $this->genera($utente, $this->a);
        $this->genera($utente, $this->b);
        $this->genera($utente, $this->b);

        $orari = Orario::query()->withoutGlobalScopes()->orderBy('id')->get();
        $this->assertSame([[$this->a->id, 1], [$this->b->id, 1], [$this->b->id, 2]], $orari->map(fn ($o) => [$o->sede_id, $o->versione])->all());
        $this->assertSame(['completata'], Generazione::query()->withoutGlobalScopes()->pluck('stato')->unique()->all());

        // ciascun orario contiene solo i dati della propria sede
        app(SedeCorrente::class)->imposta(null);
        foreach ($orari as $orario) {
            $docenti = $orario->lezioni()->withoutGlobalScopes()->with('cattedra.docente')->get()->pluck('cattedra.docente.cognome')->unique()->all();
            $this->assertSame([$orario->sede_id === $this->a->id ? 'Alfa' : 'Beta'], $docenti);
        }

        // nella sede B si vedono solo i suoi orari; quello dell'altra sede non si apre
        $utente->get('/orari')->assertOk()->assertSee('Orario v2')->assertSee('Orario v1');
        $this->assertSame(2, Orario::query()->count());
        $utente->get("/orari/{$orari[0]->id}/controllo")->assertNotFound();
    }

    public function test_la_pubblicazione_archivia_solo_gli_orari_della_stessa_sede(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $this->genera($utente, $this->a);
        $this->genera($utente, $this->b);
        $this->genera($utente, $this->b);
        [$inA, $b1, $b2] = Orario::query()->withoutGlobalScopes()->orderBy('id')->get();

        foreach ([$inA, $b1] as $orario) {
            $orario->update(['stato' => 'pubblicato']);
        }
        $b2->update(['stato' => 'approvato']);

        $utente->post("/orari/{$b2->id}/stato", ['stato' => 'pubblicato'])->assertRedirect();

        $this->assertSame('pubblicato', Orario::query()->withoutGlobalScopes()->find($inA->id)->stato);   // l'altra sede non si tocca
        $this->assertSame('archiviato', Orario::query()->withoutGlobalScopes()->find($b1->id)->stato);
        $this->assertSame('pubblicato', Orario::query()->withoutGlobalScopes()->find($b2->id)->stato);
    }

    public function test_i_pdf_riportano_la_sede_solo_con_piu_sedi(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $this->genera($utente, $this->b);
        $orario = Orario::query()->firstOrFail();

        $html = app(OrarioPdfExporter::class)->generale($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Quadro generale orario', $html);
        $this->assertStringContainsString('Succursale', $html);

        $this->a->delete();
        $html = app(OrarioPdfExporter::class)->generale($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Quadro generale orario', $html);
        $this->assertStringNotContainsString('Succursale', $html);
    }

    public function test_l_import_dei_dati_aspetta_anche_le_generazioni_delle_altre_sedi(): void
    {
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $utente->post('/sede', ['sede_id' => $this->a->id]);
        $orario = Orario::factory()->create();
        app(SedeCorrente::class)->imposta($this->b->id);
        Generazione::query()->create(['periodo_id' => $orario->periodo_id, 'seed' => 1, 'time_limit_s' => 10, 'stato' => 'in_corso']);
        $zip = (new DatiScuola)->esporta(['sedi']);
        app(SedeCorrente::class)->imposta($this->a->id);   // si lavora nella sede A: la generazione è della B

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('generazione in corso');
        (new DatiScuola)->importa($zip, ['sedi']);
    }
}
