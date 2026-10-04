<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_esporta_lorario_di_una_classe_in_pdf(): void
    {
        $classe = Classe::factory()->create();
        $slot = Slot::factory()->create();
        $classe->slotAttivi()->attach($slot->id);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/classe/{$classe->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_esporta_lorario_di_un_docente_in_pdf(): void
    {
        $docente = Docente::factory()->create();
        $orario = Orario::factory()->create();
        Cattedra::factory()->create(['docente_id' => $docente->id]);

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/docente/{$docente->id}");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_esporta_il_tabellone_generale_in_pdf(): void
    {
        $classe = Classe::factory()->create();
        $orario = Orario::factory()->create();

        $utente = User::factory()->create(['ruolo' => 'ds']);

        $response = $this->actingAs($utente)->get("/orari/{$orario->id}/export/generale");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_il_tabellone_sta_su_un_foglio_senza_ore_vuote_e_mostra_il_sostegno(): void
    {
        $classe = Classe::factory()->create();
        $lunedi = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $martedi = Slot::factory()->create(['giorno' => 2, 'ordine' => 1]);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 2]); // nessuna lezione: la colonna non compare
        Slot::factory()->create(['giorno' => 3, 'ordine' => 1]); // giorno senza lezioni: nessuna colonna
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        foreach ([$lunedi, $martedi] as $slot) {
            Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);
        }
        $sostegno = Docente::factory()->create(['cognome' => 'Verdi', 'tipo_posto' => 'sostegno']);
        \App\Models\CompresenzaSostegno::query()->create([
            'orario_id' => $orario->id, 'docente_id' => $sostegno->id, 'classe_id' => $classe->id, 'slot_id' => $lunedi->id,
        ]);

        $html = app(\App\Services\Export\OrarioPdfExporter::class)->generale($orario)->getDomPDF()->outputHtml();

        $this->assertStringContainsString('Luned&igrave;', $html);
        $this->assertStringContainsString('Marted&igrave;', $html);
        $this->assertStringNotContainsString('Mercoled&igrave;', $html);
        $this->assertStringNotContainsString('2&ordf;</th>', $html); // la 2ª ora è vuota ovunque
        $this->assertStringNotContainsString('page-break', $html);   // un solo foglio
        $this->assertStringContainsString('S Verdi', $html);         // sostegno visibile
        $this->assertStringContainsString($cattedra->disciplina->codice, $html);
    }

    public function test_la_griglia_della_classe_si_ferma_allultima_ora_attiva(): void
    {
        $classe = Classe::factory()->create();
        foreach ([1, 2, 7] as $ordine) {
            $slot = Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine]);
            if ($ordine <= 2) {
                $classe->slotAttivi()->attach($slot->id);
            }
        }
        $orario = Orario::factory()->create();

        $html = app(\App\Services\Export\OrarioPdfExporter::class)->classe($orario, $classe)->getDomPDF()->outputHtml();

        $this->assertStringContainsString('2&ordf;', $html);
        $this->assertStringNotContainsString('7&ordf;', $html);
    }

    public function test_il_pdf_delle_classi_ha_un_foglio_per_classe_con_il_titolo_centrato(): void
    {
        $prima = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A']);
        $seconda = Classe::factory()->create(['anno_corso' => 2, 'sezione' => 'B']);
        $slot = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $prima->slotAttivi()->attach($slot->id);
        $seconda->slotAttivi()->attach($slot->id);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $prima->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);
        $sostegno = Docente::factory()->create(['cognome' => 'Verdi']);
        \App\Models\CompresenzaSostegno::query()->create([
            'orario_id' => $orario->id, 'docente_id' => $sostegno->id, 'classe_id' => $prima->id, 'slot_id' => $slot->id,
        ]);

        $pdf = app(\App\Services\Export\OrarioPdfExporter::class)->classi($orario);
        $html = $pdf->getDomPDF()->outputHtml();

        // dompdf scrive la "ª" come entità HTML
        $html = str_replace('&ordf;', 'ª', $html);
        $this->assertStringContainsString('Orario classe '.$prima->nomeCompleto(), $html);
        $this->assertStringContainsString('Orario classe '.$seconda->nomeCompleto(), $html);
        $this->assertSame(1, substr_count($html, 'page-break-after: always')); // 2 classi = 1 interruzione di pagina
        $this->assertStringContainsString('text-align: center', $html);          // titolo centrato
        $this->assertStringContainsString('S Verdi', $html);                     // sostegno nella classe

        $risposta = $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get("/orari/{$orario->id}/export/classi");
        $risposta->assertOk();
        $this->assertSame('application/pdf', $risposta->headers->get('Content-Type'));
    }

    public function test_i_pdf_mostrano_orari_delle_ore_e_ricreazioni(): void
    {
        $classe = Classe::factory()->create();
        $prima = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '09:40:00', 'fine' => '10:30:00', 'intervallo_dopo' => true]);
        $seconda = Slot::factory()->create(['giorno' => 1, 'ordine' => 2, 'inizio' => '10:40:00', 'fine' => '11:30:00']);
        $classe->slotAttivi()->attach([$prima->id, $seconda->id]);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
        foreach ([$prima, $seconda] as $slot) {
            Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);
        }
        $esporta = app(\App\Services\Export\OrarioPdfExporter::class);

        $griglia = $esporta->classe($orario, $classe)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('09:40-10:30', $griglia);
        $this->assertStringContainsString('Ricreazione 10:30-10:40', $griglia);
        $this->assertStringContainsString('10 minuti', $griglia);

        $tabellone = $esporta->generale($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('1&ordf; 09:40-10:30', $tabellone);
        $this->assertStringContainsString('ricreazione</strong> 10:30-10:40 (10\')', $tabellone);
    }
}
