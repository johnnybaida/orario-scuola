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
        $this->assertStringContainsString('Ricreazione<br><span class="orario">10:30-10:40 (10\')</span>', $griglia);   // riga della pausa: etichetta, orario e durata

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

    public function test_l_aula_nei_pdf_porta_il_piano_se_indicato(): void
    {
        $this->assertSame('2° piano', \App\Models\Aula::factory()->make(['piano' => 2])->etichettaPiano());
        $this->assertSame('piano terra', \App\Models\Aula::factory()->make(['piano' => 0])->etichettaPiano());
        $this->assertSame('interrato', \App\Models\Aula::factory()->make(['piano' => -1])->etichettaPiano());
        $this->assertSame('P2', \App\Models\Aula::factory()->make(['piano' => 2])->etichettaPiano(true));
        $this->assertSame('PT', \App\Models\Aula::factory()->make(['piano' => 0])->etichettaPiano(true));
        $this->assertNull(\App\Models\Aula::factory()->make(['piano' => null])->etichettaPiano());
        $this->assertSame('Human Lab (2° piano)', \App\Models\Aula::factory()->make(['nome' => 'Human Lab', 'piano' => 2])->nomeConPiano());
        $this->assertSame('Palestra', \App\Models\Aula::factory()->make(['nome' => 'Palestra', 'piano' => null])->nomeConPiano());
    }

    public function test_la_pausa_mostra_chi_la_sorveglia_e_la_mensa_senza_ora_compare_nei_fogli(): void
    {
        foreach ([1, 2] as $ordine) {
            Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine, 'inizio' => $ordine === 1 ? '08:00:00' : '09:30:00', 'fine' => $ordine === 1 ? '08:50:00' : '10:20:00',
                'ricreazione_minuti' => $ordine === 1 ? 40 : null, 'ricreazione_nome' => $ordine === 1 ? 'Mensa' : null]);
        }
        $classe = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'C']);
        $classe->slotAttivi()->sync(Slot::query()->pluck('id'));
        $orario = Orario::factory()->create();
        $rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $verdi = Docente::factory()->create(['cognome' => 'Verdi', 'nome' => 'Luca']);
        $rossi->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);
        $verdi->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);
        $rossi->assistenzePausa()->create(['giorno' => 2, 'ordine' => 9]);   // pausa che non esiste: non compare
        $pranzo = \App\Models\Disciplina::factory()->create(['nome' => 'Pranzo', 'senza_slot' => true]);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $rossi->id, 'disciplina_id' => $pranzo->id, 'ore' => 2]);

        $esportatore = app(\App\Services\Export\OrarioPdfExporter::class);
        $classi = $esportatore->classi($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('<td>Rossi Anna, Verdi Luca</td>', $classi);   // la cella del lunedì nella riga della pausa
        $this->assertStringNotContainsString('Sorveglianza:', $classi);
        $this->assertStringContainsString('Mensa e attivit&agrave; senza ora:', $classi);
        $this->assertStringContainsString('Pranzo: Rossi Anna (2h)', $classi);

        // senza assistenze, nei giorni di rientro la riga della pausa mostra i docenti della mensa della classe
        \App\Models\AssistenzaPausa::query()->get()->each->delete();
        $this->assertStringContainsString('<td>Pranzo: Rossi Anna</td>', $esportatore->classi($orario)->getDomPDF()->outputHtml());

        $docenti = $esportatore->docenti($orario, collect([$rossi]))->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Pranzo in 1&ordf; C (2h)', str_replace('ª', '&ordf;', $docenti));
    }

    public function test_il_tabellone_mostra_la_disciplina_senza_ora_nella_colonna_della_sua_pausa(): void
    {
        foreach ([1, 2] as $giorno) {
            foreach ([1, 2] as $ordine) {
                Slot::query()->create(['giorno' => $giorno, 'ordine' => $ordine, 'inizio' => $ordine === 1 ? '08:00:00' : '09:30:00', 'fine' => $ordine === 1 ? '08:50:00' : '10:20:00',
                    'intervallo_dopo' => $ordine === 1, 'ricreazione_minuti' => $ordine === 1 ? 40 : null, 'ricreazione_nome' => $ordine === 1 ? 'Mensa' : null]);
            }
        }
        $tempoPieno = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'C']);   // rientro il lunedì (ore 1 e 2)
        $tempoPieno->slotAttivi()->sync(Slot::query()->where('giorno', 1)->pluck('id')->merge(Slot::query()->where('giorno', 2)->where('ordine', 1)->pluck('id')));
        $orario = Orario::factory()->create();
        $rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $pranzo = \App\Models\Disciplina::factory()->create(['codice' => 'PRA', 'nome' => 'Pranzo', 'senza_slot' => true, 'pausa_dopo_ora' => 1]);
        Cattedra::factory()->create(['classe_id' => $tempoPieno->id, 'docente_id' => $rossi->id, 'disciplina_id' => $pranzo->id, 'ore' => 2]);
        $ita = Cattedra::factory()->create(['classe_id' => $tempoPieno->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $ita->id, 'slot_id' => Slot::query()->where('giorno', 1)->where('ordine', 1)->first()->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $ita->id, 'slot_id' => Slot::query()->where('giorno', 2)->where('ordine', 1)->first()->id]);
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $ita->id, 'slot_id' => Slot::query()->where('giorno', 1)->where('ordine', 2)->first()->id]);

        $html = app(\App\Services\Export\OrarioPdfExporter::class)->generale($orario)->getDomPDF()->outputHtml();

        $this->assertSame(2, preg_match_all('/<th class="[^"]*pausa">Mensa<\/th>/', $html));   // la colonna della pausa compare nei due giorni
        $this->assertStringContainsString('PRA', $html);
        $this->assertStringContainsString('Rossi', $html);
        // il martedì la classe non ha ore dopo la pausa: la cella della mensa è vuota (una sola cella con la disciplina)
        $this->assertSame(1, substr_count($html, '<div class="materia">PRA</div>'));
    }

    public function test_i_pulsanti_dei_pdf_si_aprono_in_una_nuova_scheda(): void
    {
        $orario = Orario::factory()->create();
        $classe = Classe::factory()->create();
        $slot = Slot::factory()->create();
        $classe->slotAttivi()->attach($slot->id);
        $utente = $this->actingAs(User::factory()->create(['ruolo' => 'ds']));

        $elenco = $utente->get('/orari')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(5, preg_match_all('/href="[^"]*\/export\/[^"]*"\s+target="_blank"\s+rel="noopener"/', $elenco));   // tabelloni, classi, docenti, aule
        $this->assertSame(0, preg_match_all('/href="[^"]*\/export\/[^"]*"\s+class=/', $elenco));                                            // nessun link PDF senza _blank

        $this->get("/orari/{$orario->id}/classe/{$classe->id}")->assertOk()->assertSee('target="_blank" rel="noopener" class="text-sm underline text-gray-600">Esporta PDF', false);
    }
}
