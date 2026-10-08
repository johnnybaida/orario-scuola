<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use App\Services\AssistenzaPause;
use App\Services\Export\OrarioPdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PausaPrimaTest extends TestCase
{
    use RefreshDatabase;

    private function scuola(): void
    {
        foreach ([1, 2] as $giorno) {
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 2, 'inizio' => '08:50:00', 'fine' => '09:40:00']);
        }
    }

    private function salva(array $pausaPrima, string $inizio = '08:00')
    {
        return $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->put('/scansione-oraria', [
            'ore' => [1 => ['inizio' => $inizio, 'fine' => '08:50'], 2 => ['inizio' => '08:50', 'fine' => '09:40']],
            'pausa_prima' => $pausaPrima,
        ]);
    }

    public function test_si_imposta_una_pausa_prima_della_prima_ora_su_tutti_i_giorni_e_si_toglie(): void
    {
        $this->scuola();

        $this->salva(['minuti' => '10', 'nome' => 'Accoglienza'])->assertSessionHasNoErrors();
        $this->assertSame(2, Slot::query()->where('ordine', 1)->where('pausa_prima_minuti', 10)->where('pausa_prima_nome', 'Accoglienza')->count());
        $this->assertSame(0, Slot::query()->where('ordine', 2)->whereNotNull('pausa_prima_minuti')->count());   // solo la prima ora
        $primo = Slot::query()->where('ordine', 1)->firstOrFail();
        $this->assertSame('07:50', $primo->inizioPausaPrima());
        $this->get('/scansione-oraria')->assertOk()->assertSee('Prima della 1ª')->assertSee('07:50–08:00')->assertSee('value="Accoglienza"', false);

        $this->salva(['minuti' => '', 'nome' => 'Accoglienza']);   // senza minuti il nome si perde
        $this->assertSame(0, Slot::query()->whereNotNull('pausa_prima_minuti')->count());
        $this->assertNull(Slot::query()->where('ordine', 1)->first()->pausa_prima_nome);
        $this->assertSame('Pausa', Slot::query()->where('ordine', 1)->first()->nomePausaPrima());
    }

    public function test_la_pausa_non_puo_cominciare_prima_di_mezzanotte(): void
    {
        $this->scuola();

        $this->salva(['minuti' => '20'], '00:10')->assertSessionHasErrors('pausa_prima.minuti');
        $this->assertSame(0, Slot::query()->whereNotNull('pausa_prima_minuti')->count());
    }

    public function test_un_docente_puo_assistere_la_pausa_prima_della_prima_ora(): void
    {
        $this->scuola();
        $this->salva(['minuti' => '10', 'nome' => 'Accoglienza']);
        $docente = Docente::factory()->create();

        $pause = app(AssistenzaPause::class)->pause();
        $this->assertSame([0], $pause->keys()->all());
        $this->assertStringContainsString('prima della 1ª ora', $pause[0]['etichetta']);

        $this->put("/docenti/{$docente->id}", [
            'nome' => $docente->nome, 'cognome' => $docente->cognome, 'tipo_contratto' => $docente->tipo_contratto, 'tipo_posto' => $docente->tipo_posto,
            'regime' => $docente->regime, 'ore_dovute' => $docente->ore_dovute, 'assistenze_inviate' => 1, 'assistenze' => [['giorno' => 2, 'ordine' => 0]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Mar · Accoglienza 07:50–08:00'], app(AssistenzaPause::class)->elenco($docente->fresh()->load('assistenzePausa')));
        $this->assertDatabaseHas('audit_log', ['entita' => 'AssistenzaPausa', 'etichetta' => $docente->nomeCompleto().' – Martedì, pausa prima della prima ora']);
    }

    public function test_compare_nei_pdf_e_si_esporta_e_importa_in_csv(): void
    {
        $this->scuola();
        $this->salva(['minuti' => '10', 'nome' => 'Accoglienza']);
        $orario = Orario::factory()->create();
        $cattedra = Cattedra::factory()->create();
        Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => Slot::query()->where('ordine', 2)->first()->id]);
        $esporta = app(OrarioPdfExporter::class);

        $griglia = $esporta->classe($orario, $cattedra->classe)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Accoglienza 07:50-08:00', $griglia);
        $this->assertStringContainsString('10 minuti', $griglia);
        $tabellone = $esporta->generale($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('accoglienza</strong> 07:50-08:00 (10\')', $tabellone);

        $csv = $this->get('/csv/scansione')->streamedContent();
        $this->assertStringContainsString('pausa_prima_minuti;pausa_prima_nome', $csv);
        $this->assertStringContainsString("1;08:00;08:50;;;10;Accoglienza", $csv);

        $this->salva(['minuti' => '', 'nome' => '']);
        $this->post('/csv/scansione/importa', ['file' => UploadedFile::fake()->createWithContent('scansione.csv', $csv)])->assertOk()->assertSee('Importate 2 righe');
        $this->assertSame(10, (int) Slot::query()->where('ordine', 1)->first()->pausa_prima_minuti);
    }
}
