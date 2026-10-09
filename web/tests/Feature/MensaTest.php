<?php

namespace Tests\Feature;

use App\Models\AssistenzaPausa;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Orario;
use App\Models\QuadroOrario;
use App\Models\Slot;
use App\Models\User;
use App\Services\Export\OrarioPdfExporter;
use App\Services\Mensa;
use App\Services\Validation\PreValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** La mensa come pausa: classi in mensa ricavate dai rientri, docenti per classe e giorno, avvisi, PDF e CSV. */
class MensaTest extends TestCase
{
    use RefreshDatabase;

    private Classe $c1;

    private Classe $c2;

    private Docente $rossi;

    private Docente $verdi;

    private Aula $aula;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    /** Lunedì e mercoledì: ore 1-2 al mattino, pausa-mensa dopo la 2ª, ora 3 dopo la pausa. 1ªC e 2ªC hanno il rientro (ora 3) il lunedì e il mercoledì. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->aula = Aula::factory()->create(['nome' => 'Auditorium', 'tipo' => 'pausa', 'capienza' => 1, 'piano' => 3]);
        foreach ([1, 3] as $giorno) {
            foreach ([1, 2, 3] as $ordine) {
                Slot::query()->create([
                    'giorno' => $giorno, 'ordine' => $ordine, 'inizio' => sprintf('%02d:00:00', 7 + $ordine), 'fine' => sprintf('%02d:50:00', 7 + $ordine), 'intervallo_dopo' => $ordine === 2,
                    'ricreazione_minuti' => $ordine === 2 ? 50 : null, 'ricreazione_nome' => $ordine === 2 ? 'Pranzo' : null, 'ricreazione_mensa' => $ordine === 2,
                    'ricreazione_aula_id' => $ordine === 2 ? $this->aula->id : null,
                ]);
            }
        }
        $quadro = QuadroOrario::factory()->create(['ore_totali' => 6, 'ore_mensa' => 2]);   // 4 ore di discipline + 2 di mensa
        $this->c1 = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'C', 'quadro_orario_id' => $quadro->id]);
        $this->c2 = Classe::factory()->create(['anno_corso' => 2, 'sezione' => 'C', 'quadro_orario_id' => $quadro->id]);
        foreach ([$this->c1, $this->c2] as $c) {
            $c->slotAttivi()->sync(Slot::query()->pluck('id'));
        }
        $this->rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $this->verdi = Docente::factory()->create(['cognome' => 'Verdi', 'nome' => 'Luca']);
    }

    public function test_le_classi_in_mensa_si_ricavano_dai_giorni_di_rientro(): void
    {
        $mensa = app(Mensa::class);
        $this->assertSame([1], $mensa->pause()->keys()->all() === [2] ? [1] : []);   // la pausa è dopo la 2ª ora
        $this->assertSame([1, 3], $mensa->giorni($this->c1->load('slotAttivi'), 2));

        $this->c1->slotAttivi()->detach(Slot::query()->where('giorno', 3)->where('ordine', 3)->value('id'));   // il mercoledì senza ora dopo la pausa
        $this->assertSame([1], $mensa->giorni($this->c1->fresh('slotAttivi'), 2));
    }

    public function test_si_assegnano_i_docenti_dalla_pagina_mensa_e_si_rileggono(): void
    {
        $celle = ['2' => [
            '1' => [$this->c1->id => [$this->rossi->id, ''], $this->c2->id => [$this->rossi->id]],
            '3' => [$this->c1->id => [$this->verdi->id], $this->c2->id => []],
        ]];
        $this->actingAs($this->referente())->put('/mensa', ['celle' => $celle])->assertRedirect(route('mensa.index'));

        $this->assertSame(2, AssistenzaPausa::query()->count());   // Rossi lunedì (con 1ªC e 2ªC) e Verdi mercoledì (con 1ªC)
        $rossi = AssistenzaPausa::query()->where('docente_id', $this->rossi->id)->first();
        $this->assertEqualsCanonicalizing([$this->c1->id, $this->c2->id], $rossi->classi->pluck('id')->all());
        $this->assertSame([$this->c1->id], AssistenzaPausa::query()->where('docente_id', $this->verdi->id)->first()->classi->pluck('id')->all());

        $this->actingAs($this->referente())->get('/mensa')->assertOk()->assertSee('Pranzo')->assertSee('Rossi Anna')->assertSee('Verdi Luca');

        // togliendo il docente dal lunedì di 2ªC resta con 1ªC; svuotando la cella del lunedì di 1ªC l'assistenza sparisce
        $this->actingAs($this->referente())->put('/mensa', ['celle' => ['2' => ['1' => [$this->c1->id => [], $this->c2->id => []]]]]);
        $this->assertSame(1, AssistenzaPausa::query()->count());
        $this->assertDatabaseMissing('assistenze_pausa', ['docente_id' => $this->rossi->id]);
    }

    public function test_il_monte_ore_conta_una_volta_per_giorno_anche_con_piu_classi(): void
    {
        $this->actingAs($this->referente())->put('/mensa', ['celle' => ['2' => ['1' => [$this->c1->id => [$this->rossi->id], $this->c2->id => [$this->rossi->id]]]]]);

        $this->assertSame(1.0, app(\App\Services\AssistenzaPause::class)->ore($this->rossi->fresh()));   // 50' contano 1 ora, una sola volta per lunedì
    }

    public function test_avvisi_classe_senza_docente_aula_piccola_e_ore_di_mensa_del_quadro(): void
    {
        $avvisi = array_column((new PreValidator)->avvisi(), 'testo');
        $this->assertTrue(collect($avvisi)->contains(fn ($t) => str_contains($t, 'Classe 1ª C: in mensa lunedì e mercoledì ma senza un docente')));
        $this->assertTrue(collect($avvisi)->contains(fn ($t) => str_contains($t, 'ci sono 2 classi ma l\'aula Auditorium ne ospita 1')));   // capienza 1, due classi

        $this->c1->slotAttivi()->detach(Slot::query()->where('giorno', 3)->where('ordine', 3)->value('id'));   // 1ªC in mensa 1 giorno su 2 ore di mensa del quadro
        $avvisi = array_column((new PreValidator)->avvisi(), 'testo');
        $this->assertTrue(collect($avvisi)->contains(fn ($t) => str_contains($t, 'Classe 1ª C: il quadro orario prevede 2h di mensa ma la classe è in mensa 1 giorni')));

        $this->assertSame([], array_values(array_intersect($avvisi, (new PreValidator)->esegui())));   // gli avvisi non sono problemi bloccanti
    }

    public function test_gli_slot_attivi_sono_le_ore_delle_discipline_senza_le_ore_di_mensa(): void
    {
        $quadro = $this->c1->quadroOrario;
        foreach ([1, 2, 3, 4] as $i) {   // 4 ore di discipline
            $d = \App\Models\Disciplina::factory()->create();
            $quadro->righe()->create(['disciplina_id' => $d->id, 'ore_settimanali' => 1]);
            Cattedra::factory()->create(['classe_id' => $this->c1->id, 'disciplina_id' => $d->id, 'ore' => 1]);
        }
        $problemi = array_column((new PreValidator)->problemi(), 'testo');
        // 6 slot attivi, ma servono 4 ore di discipline (6 del quadro - 2 di mensa)
        $this->assertTrue(collect($problemi)->contains(fn ($t) => str_contains($t, 'Classe 1ª C: ha 6 slot attivi ma ne servono 4: il quadro è di 6h e 2h sono di mensa')));

        $this->c1->slotAttivi()->sync(Slot::query()->where('ordine', '<=', 2)->pluck('id')->merge(Slot::query()->where('ordine', 3)->where('giorno', 1)->pluck('id'))->take(4));
        $this->assertFalse(collect(array_column((new PreValidator)->problemi(), 'testo'))->contains(fn ($t) => str_contains($t, 'Classe 1ª C: ha')));
    }

    public function test_i_pdf_mostrano_i_sorveglianti_per_classe_e_giorno(): void
    {
        $this->actingAs($this->referente())->put('/mensa', ['celle' => ['2' => [
            '1' => [$this->c1->id => [$this->rossi->id], $this->c2->id => [$this->verdi->id]],
            '3' => [$this->c1->id => [$this->rossi->id], $this->c2->id => [$this->rossi->id]],
        ]]]);
        $orario = Orario::factory()->create();
        $esporta = app(OrarioPdfExporter::class);

        $c1 = $esporta->classe($orario, $this->c1)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('<td>Rossi Anna</td>', $c1);        // 1ªC: Rossi il lunedì e il mercoledì
        $this->assertStringNotContainsString('Verdi Luca', $c1);              // Verdi è con la 2ªC
        $this->assertStringContainsString('Auditorium (3&deg; piano)', $c1);

        $docente = $esporta->docenti($orario, collect([$this->rossi]))->getDomPDF()->outputHtml();
        $this->assertStringContainsString('Rossi Anna (1&ordf; C)', str_replace('ª', '&ordf;', $docente));   // il lunedì solo la 1ªC
        $this->assertStringContainsString('Rossi Anna (1&ordf; C, 2&ordf; C)', str_replace('ª', '&ordf;', $docente));   // il mercoledì entrambe

        // il tabellone mostra solo giorni e ore con lezioni: due lezioni della 1ªC bastano a definire lunedì e mercoledì, ore 1-3
        $cattedra = Cattedra::factory()->create(['classe_id' => $this->c1->id]);
        foreach ([[1, 1], [3, 3]] as [$g, $o]) {
            \App\Models\Lezione::factory()->create(['orario_id' => $orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => Slot::query()->where('giorno', $g)->where('ordine', $o)->value('id')]);
        }
        $tabellone = $esporta->generale($orario)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('<div class="materia">Pranzo</div>', $tabellone);
        $this->assertStringContainsString('<div>Rossi</div>', $tabellone);
        $this->assertStringContainsString('<div>Verdi</div>', $tabellone);
    }

    public function test_csv_della_scansione_dei_quadri_e_delle_assistenze_portano_la_mensa(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        AssistenzaPausa::query()->create(['docente_id' => $this->rossi->id, 'giorno' => 1, 'ordine' => 2])->classi()->sync([$this->c1->id, $this->c2->id]);

        $scansione = $this->get('/csv/scansione')->streamedContent();
        $this->assertStringContainsString(';1', explode("\n", $scansione)[2] ?? '');   // la riga della 2ª ora ha mensa = 1
        $quadri = $this->get('/csv/quadri-orari')->streamedContent();
        $this->assertStringContainsString(';2', $quadri);                                // ore_mensa
        $assistenze = $this->get('/csv/assistenze-pausa')->streamedContent();
        $this->assertStringContainsString('Rossi;Anna;LUN;2;1C|2C', $assistenze);

        AssistenzaPausa::query()->get()->each->delete();
        $this->post('/csv/assistenze-pausa/importa', ['file' => UploadedFile::fake()->createWithContent('a.csv', $assistenze)])->assertOk();
        $this->assertEqualsCanonicalizing([$this->c1->id, $this->c2->id], AssistenzaPausa::query()->first()->classi->pluck('id')->all());
    }

    public function test_la_pagina_dice_cosa_manca_e_la_scansione_permette_di_segnare_la_mensa(): void
    {
        Slot::query()->update(['ricreazione_mensa' => false]);
        $this->actingAs($this->referente())->get('/mensa')->assertOk()->assertSee('Nessuna pausa è segnata come mensa', false)->assertSee('Quando una pausa sarà segnata come mensa', false);

        $ore = [1 => ['inizio' => '08:00', 'fine' => '08:50'], 2 => ['inizio' => '09:00', 'fine' => '09:50', 'ricreazione' => 50, 'nome' => 'Pranzo', 'mensa' => 1], 3 => ['inizio' => '10:40', 'fine' => '11:30']];
        $this->actingAs($this->referente())->put('/scansione-oraria', ['ore' => $ore])->assertSessionHasNoErrors();
        $this->assertSame(2, Slot::query()->where('ricreazione_mensa', true)->count());   // lunedì e mercoledì

        $this->get('/mensa')->assertOk()->assertSee('1ª C')->assertSee('— docente —', false);
    }

    public function test_il_messaggio_sulle_ore_del_quadro_suggerisce_le_ore_di_mensa_e_la_pagina_controlla_gli_slot(): void
    {
        $this->c1->quadroOrario->update(['ore_mensa' => 0]);   // la mensa non è dichiarata nel quadro
        $problemi = array_column((new PreValidator)->problemi(), 'testo');
        $this->assertTrue(collect($problemi)->contains(fn ($t) => str_contains($t, 'Classe 1ª C: il quadro orario prevede 6h ma le cattedre assegnate coprono 0h.')
            && str_contains($t, 'scrivile in «Ore di mensa» nel quadro orario')));

        $this->c2->slotAttivi()->detach(Slot::query()->where('giorno', 3)->where('ordine', 1)->value('id'));   // 5 slot attivi su 6 ore di quadro
        $controlli = collect(app(Mensa::class)->controlli())->pluck('testo')->implode(' | ');
        $this->assertStringContainsString('Slot attivi da correggere (devono essere le ore di discipline, senza la mensa): 2ª C ne ha 5, ne servono 6', $controlli);
    }

    public function test_il_totale_della_scheda_classe_somma_le_ore_di_mensa_del_quadro(): void
    {
        $this->actingAs($this->referente())->get("/classi/{$this->c1->id}/edit")->assertOk()
            ->assertSee('data-totale="cattedre+mensa"', false)->assertSee('<input type="hidden" data-somma="mensa" value="2">', false)
            ->assertSee('di discipline + 2 di mensa', false);

        $this->c1->quadroOrario->update(['ore_mensa' => 0]);
        $this->actingAs($this->referente())->get("/classi/{$this->c1->id}/edit")->assertOk()->assertDontSee('di mensa)', false);
    }
}
