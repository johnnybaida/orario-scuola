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
        $prima = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '09:40:00', 'fine' => '10:30:00', 'intervallo_dopo' => true, 'ricreazione_minuti' => 10]);
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

    public function test_il_pdf_dei_docenti_ha_un_foglio_per_docente_con_lezioni_e_sostegno(): void
    {
        $classe = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A']);
        $slot1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $slot2 = Slot::factory()->create(['giorno' => 1, 'ordine' => 2]);
        $orario = Orario::factory()->create();
        $rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $verdi = Docente::factory()->create(['cognome' => 'Verdi', 'nome' => 'Luca']);
        Docente::factory()->create(['cognome' => 'Senzaore']);
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $rossi->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot1->id]);
        \App\Models\CompresenzaSostegno::query()->create(['orario_id' => $orario->id, 'docente_id' => $verdi->id, 'classe_id' => $classe->id, 'slot_id' => $slot2->id]);

        $html = app(\App\Services\Export\OrarioPdfExporter::class)->docenti($orario)->getDomPDF()->outputHtml();
        $html = str_replace('&ordf;', 'ª', $html); // dompdf scrive la "ª" come entità HTML

        $this->assertStringContainsString('Orario docente '.$rossi->nomeCompleto(), $html);
        $this->assertStringContainsString('Orario docente '.$verdi->nomeCompleto(), $html);
        $this->assertStringNotContainsString('Senzaore', $html);                       // nessuna ora: niente foglio
        $this->assertStringContainsString($classe->nomeCompleto().' - '.$cattedra->disciplina->nome, $html);
        $this->assertStringContainsString('S '.$classe->nomeCompleto(), $html);        // sostegno in compresenza
        $this->assertSame(1, substr_count($html, 'page-break-after: always'));         // 2 docenti = 1 interruzione
        $this->assertLessThan(strpos($html, 'Verdi'), strpos($html, 'Rossi'));         // ordine alfabetico

        $risposta = $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get("/orari/{$orario->id}/export/docenti");
        $risposta->assertOk();
        $this->assertSame('application/pdf', $risposta->headers->get('Content-Type'));
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get("/orari/{$orario->id}/export/docenti")->assertForbidden();
    }

    /** Un foglio per classe, qualunque sia il numero di ore e la lunghezza dei testi: mai due pagine per la stessa classe. */
    public function test_il_pdf_delle_classi_ha_una_sola_pagina_per_classe_anche_con_9_ore_e_testi_lunghi(): void
    {
        $orario = Orario::factory()->create();
        $slot = [];
        foreach (range(1, 5) as $giorno) {
            foreach (range(1, 9) as $ordine) {
                $slot[$giorno][$ordine] = Slot::query()->create([
                    'giorno' => $giorno, 'ordine' => $ordine, 'inizio' => '08:00:00', 'fine' => '08:50:00',
                    'intervallo_dopo' => in_array($ordine, [3, 6], true), 'ricreazione_minuti' => in_array($ordine, [3, 6], true) ? 10 : null,
                ]);
            }
        }

        foreach ([6, 9, 9] as $i => $ore) {
            $classe = Classe::factory()->create();
            $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id]);
            $cattedra->disciplina->update(['nome' => 'Disciplina con un nome molto lungo che va a capo su più righe']);
            foreach ($slot as $perGiorno) {
                foreach (range(1, $ore) as $ordine) {
                    $classe->slotAttivi()->attach($perGiorno[$ordine]->id);
                    Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $perGiorno[$ordine]->id]);
                }
            }
        }

        $pdf = app(\App\Services\Export\OrarioPdfExporter::class)->classi($orario)->output();

        $this->assertSame(3, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdf));

        // Aula con 3 lezioni contemporanee in ogni ora (capienza 3): anche così una sola pagina.
        $aula = \App\Models\Aula::factory()->create(['capienza' => 3]);
        Lezione::query()->where('orario_id', $orario->id)->update(['aula_id' => $aula->id]);
        $pdfAula = app(\App\Services\Export\OrarioPdfExporter::class)->aule($orario)->output();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page\b(?!s)#', $pdfAula));
    }
}
