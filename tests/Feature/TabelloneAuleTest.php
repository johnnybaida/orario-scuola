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
use App\Services\Editor\SpostamentiAula;
use App\Services\Export\OrarioPdfExporter;
use App\Support\ColoriDiscipline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DADA: tabellone per classe/aula, colori per disciplina, cambi d'aula, modifica trascinando tra aule e ore. */
class TabelloneAuleTest extends TestCase
{
    use RefreshDatabase;

    private Orario $orario;

    private Slot $s1;

    private Slot $s2;

    private Slot $s3;

    private Aula $a1;

    private Aula $a2;

    private Classe $x;

    private Classe $y;

    private Disciplina $ita;

    private Disciplina $sto;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Sede::factory()->create();
        $this->orario = Orario::factory()->create();
        foreach ([1, 2, 3] as $o) {
            $this->{'s'.$o} = Slot::factory()->create(['giorno' => 1, 'ordine' => $o, 'inizio' => sprintf('%02d:00:00', 7 + $o), 'fine' => sprintf('%02d:50:00', 7 + $o)]);
        }
        $this->a1 = Aula::factory()->create(['sede_id' => $sede->id, 'nome' => 'Italiano 1', 'tipo' => 'dada_ita', 'capienza' => 1]);
        $this->a2 = Aula::factory()->create(['sede_id' => $sede->id, 'nome' => 'Italiano 2', 'tipo' => 'dada_ita', 'capienza' => 1]);
        $this->x = $this->classe($sede, 1, 'A');
        $this->y = $this->classe($sede, 1, 'B');
        $this->ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_ita']);
        $this->sto = Disciplina::factory()->create(['codice' => 'STO', 'nome' => 'Storia']);
    }

    private function classe(Sede $sede, int $anno, string $sezione): Classe
    {
        $c = Classe::factory()->create(['anno_corso' => $anno, 'sezione' => $sezione, 'sede_id' => $sede->id, 'aula_base_id' => null]);
        $c->slotAttivi()->sync([$this->s1->id, $this->s2->id, $this->s3->id]);

        return $c;
    }

    private function lez(Classe $classe, Disciplina $d, Slot $slot, ?Aula $aula, string $cognome = 'Rossi'): Lezione
    {
        $cattedra = Cattedra::factory()->create(['classe_id' => $classe->id, 'disciplina_id' => $d->id, 'ore' => 1,
            'docente_id' => Docente::factory()->create(['cognome' => $cognome])->id]);

        return Lezione::factory()->create(['orario_id' => $this->orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id, 'aula_id' => $aula?->id]);
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    private function base(): string
    {
        return "/orari/{$this->orario->id}";
    }

    // ---- punto 4: cambi d'aula ----

    public function test_riconosce_i_cambi_d_aula_solo_tra_ore_consecutive_dello_stesso_giorno(): void
    {
        $l1 = $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $l2 = $this->lez($this->x, $this->ita, $this->s2, $this->a1, 'Due');    // stessa aula: nessun cambio
        $l3 = $this->lez($this->x, $this->ita, $this->s3, $this->a2, 'Tre');    // a1 → a2: cambio
        $lezioni = Lezione::with('cattedra.classe.aulaBase', 'slot', 'aula')->whereIn('id', [$l1->id, $l2->id, $l3->id])->get();

        $cambi = app(SpostamentiAula::class)->cambi($lezioni);
        $this->assertSame([$l3->id], array_keys($cambi));
        $this->assertSame(['Italiano 1', 'Italiano 2'], [$cambi[$l3->id]['da']->nome, $cambi[$l3->id]['a']->nome]);

        // un'ora vuota in mezzo, o un altro giorno, interrompe la sequenza
        $l2->delete();
        $this->assertSame([], app(SpostamentiAula::class)->cambi(Lezione::with('cattedra.classe.aulaBase', 'slot', 'aula')->whereIn('id', [$l1->id, $l3->id])->get()));
    }

    public function test_con_aula_base_il_rientro_in_classe_dopo_la_palestra_e_un_cambio(): void
    {
        $base = Aula::factory()->create(['sede_id' => $this->x->sede_id, 'nome' => 'Aula 1A', 'tipo' => 'classe']);
        $palestra = Aula::factory()->create(['sede_id' => $this->x->sede_id, 'nome' => 'Palestra', 'tipo' => 'palestra']);
        $this->x->update(['aula_base_id' => $base->id]);
        $l1 = $this->lez($this->x, $this->sto, $this->s1, null);                 // in classe
        $l2 = $this->lez($this->x, Disciplina::factory()->create(['nome' => 'Motoria']), $this->s2, $palestra);
        $l3 = $this->lez($this->x, $this->sto, $this->s3, null, 'Altro');       // rientro in classe

        $cambi = app(SpostamentiAula::class)->cambi(Lezione::with('cattedra.classe.aulaBase', 'slot', 'aula')->whereIn('id', [$l1->id, $l2->id, $l3->id])->get());
        $this->assertEqualsCanonicalizing([$l2->id, $l3->id], array_keys($cambi));
        $this->assertSame('Aula 1A', $cambi[$l3->id]['a']->nome);

        // nella griglia la freccia compare anche sul rientro, dove l'aula (quella della classe) di norma non si scrive
        $html = $this->actingAs($this->referente())->get("{$this->base()}/classe/{$this->x->id}")->assertOk()->getContent();
        $this->assertStringContainsString('→ Aula 1A', $html);
        $this->assertStringContainsString('→ Palestra', $html);
    }

    public function test_la_griglia_della_classe_e_il_pdf_segnano_il_cambio_d_aula_con_la_freccia(): void
    {
        $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->x, $this->ita, $this->s2, $this->a2, 'Due');

        $html = $this->actingAs($this->referente())->get("{$this->base()}/classe/{$this->x->id}")->assertOk()->getContent();
        $this->assertStringContainsString('→ Italiano 2', $html);
        $this->assertStringNotContainsString('→ Italiano 1', $html);

        $pdf = str_replace('&rarr;', '→', app(OrarioPdfExporter::class)->classe($this->orario, $this->x)->getDomPDF()->outputHtml());   // dompdf scrive le frecce come entità
        $this->assertStringContainsString('→ Italiano 2', $pdf);
        $this->assertStringNotContainsString('→ Italiano 1', $pdf);
    }

    // ---- punto 3: tabellone e colori ----

    public function test_ogni_disciplina_ha_un_colore_stabile_e_diverso_dalle_altre(): void
    {
        $mappa = ColoriDiscipline::mappa();
        $this->assertSame($mappa, ColoriDiscipline::mappa());                          // stabile
        $this->assertCount(2, array_unique(array_map('json_encode', $mappa)));         // ITA e STO diversi
        $altre = Disciplina::factory()->count(11)->create();
        $tutti = ColoriDiscipline::mappa();
        $this->assertCount(count(ColoriDiscipline::PALETTE), array_unique(array_map('json_encode', array_slice($tutti, 0, 12, true))));
        $this->assertStringContainsString('background-color: ', ColoriDiscipline::stile($mappa[$this->ita->id]));
        $this->assertNotEmpty($altre);
    }

    public function test_il_tabellone_per_classe_ha_le_classi_in_riga_i_colori_e_il_conteggio_dei_cambi(): void
    {
        $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->x, $this->sto, $this->s2, null, 'Due');   // senza aula base né aula assegnata: nessun cambio rilevabile
        $this->lez($this->y, $this->ita, $this->s1, $this->a2, 'Tre');
        $this->lez($this->y, $this->ita, $this->s2, $this->a1, 'Quattro');   // cambio a2 → a1

        $html = $this->actingAs($this->referente())->get("{$this->base()}/tabellone?per=classe")->assertOk()->getContent();
        $this->assertStringContainsString('1ª A', str_replace('&ordf;', 'ª', $html));
        $this->assertStringContainsString('Cambi aula', $html);
        $this->assertStringContainsString('ITA', $html);
        $this->assertStringContainsString('STO', $html);
        $this->assertStringContainsString(ColoriDiscipline::mappa()[$this->ita->id][0], $html);   // colore della disciplina
        $this->assertStringContainsString('→ Italiano 1', $html);                                  // freccia sul cambio
        $this->assertSame(0, substr_count($html, 'draggable="true"'));                             // per classe è di sola lettura
        $this->assertStringContainsString('data-editabile="0"', $html);
        $this->assertMatchesRegularExpression('/>\s*1\s*<\/td>\s*<\/tr>/', $html);                 // 1 cambio per la classe B
    }

    public function test_il_tabellone_per_aula_ha_le_aule_in_riga_e_si_trascina_solo_in_bozza_e_con_il_permesso(): void
    {
        $libera = $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $bloccata = $this->lez($this->y, $this->ita, $this->s2, $this->a2, 'Due');
        $bloccata->update(['bloccata' => true]);
        $referente = $this->actingAs($this->referente());

        $html = $referente->get("{$this->base()}/tabellone?per=aula")->assertOk()->assertSee('Italiano 1')->assertSee('Italiano 2')->getContent();
        $this->assertStringContainsString('data-editabile="1"', $html);
        $this->assertStringContainsString('data-aula-id="'.$this->a1->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'draggable="true"'));        // la lezione bloccata non si trascina
        $this->assertStringContainsString('id="conflitti-provvisori"', $html);

        // orario non in bozza o utente senza permesso di modifica: sola lettura
        $this->orario->update(['stato' => 'approvato']);
        $html = $referente->get("{$this->base()}/tabellone?per=aula")->getContent();
        $this->assertStringContainsString('data-editabile="0"', $html);
        $this->assertSame(0, substr_count($html, 'draggable="true"'));
        $this->orario->update(['stato' => 'bozza']);
        $ds = $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get("{$this->base()}/tabellone?per=aula")->assertOk()->getContent();
        $this->assertStringContainsString('data-editabile="0"', $ds);
        $this->assertNotNull($libera);
    }

    public function test_la_vista_predefinita_e_per_aula_in_dada_e_per_classe_con_aule_base(): void
    {
        $this->lez($this->x, $this->ita, $this->s1, $this->a1);
        $referente = $this->actingAs($this->referente());
        $referente->get("{$this->base()}/tabellone")->assertOk()->assertSee('<th class="sticky left-0 z-10 bg-gray-50 border border-gray-200 px-2 py-1 text-left" rowspan="2">Aula</th>', false);

        $base = Aula::factory()->create(['sede_id' => $this->x->sede_id, 'tipo' => 'classe']);
        $this->x->update(['aula_base_id' => $base->id]);
        $this->y->update(['aula_base_id' => $base->id]);
        $referente->get("{$this->base()}/tabellone")->assertOk()->assertSee('rowspan="2">Classe</th>', false);

        $referente->get("{$this->base()}/tabellone?per=boh")->assertNotFound();
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get("{$this->base()}/tabellone")->assertForbidden();
    }

    public function test_il_tabellone_senza_lezioni_lo_dice(): void
    {
        $this->actingAs($this->referente())->get("{$this->base()}/tabellone?per=classe")->assertOk()->assertSee('non ha ancora lezioni');
    }

    public function test_il_pdf_del_tabellone_per_aula_ha_le_aule_in_riga_e_i_colori(): void
    {
        $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->y, $this->sto, $this->s1, null, 'Due');   // senza aula

        $html = app(OrarioPdfExporter::class)->generale($this->orario, 'aula')->getDomPDF()->outputHtml();
        $html = str_replace('&ordf;', 'ª', $html);
        $this->assertStringContainsString('Quadro generale orario per aula', $html);
        $this->assertStringContainsString('Italiano 1', $html);
        $this->assertStringContainsString('Senza aula', $html);
        $this->assertStringContainsString('1ª A', $html);
        $this->assertStringContainsString(ColoriDiscipline::mappa()[$this->ita->id][0], $html);

        // per classe resta il tabellone di prima, ora colorato
        $classi = str_replace('&ordf;', 'ª', app(OrarioPdfExporter::class)->generale($this->orario)->getDomPDF()->outputHtml());
        $this->assertStringContainsString('Quadro generale orario', $classi);
        $this->assertStringNotContainsString('per aula', $classi);

        $referente = $this->actingAs($this->referente());
        foreach (['', '?per=aula'] as $q) {
            $risposta = $referente->get("{$this->base()}/export/generale$q")->assertOk();
            $this->assertSame('application/pdf', $risposta->headers->get('Content-Type'));
        }
    }

    // ---- punto 5: modifica dal tabellone per aula ----

    public function test_si_cambia_l_aula_nella_stessa_ora_e_si_annulla(): void
    {
        $l = $this->lez($this->x, $this->ita, $this->s1, $this->a1);
        $referente = $this->actingAs($this->referente());

        $referente->patchJson("{$this->base()}/lezioni/{$l->id}/aula", ['aula_id' => $this->a2->id])->assertOk();
        $this->assertSame($this->a2->id, $l->fresh()->aula_id);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Lezione', 'entita_id' => $l->id, 'azione' => 'cambio_aula']);

        $referente->post("{$this->base()}/annulla-ultima")->assertSessionHas('successo');
        $this->assertSame($this->a1->id, $l->fresh()->aula_id);
        $referente->post("{$this->base()}/ripeti")->assertSessionHas('successo');
        $this->assertSame($this->a2->id, $l->fresh()->aula_id);
    }

    public function test_il_cambio_di_aula_rispetta_tipo_blocco_e_materie_senza_aula_speciale(): void
    {
        $laboratorio = Aula::factory()->create(['sede_id' => $this->x->sede_id, 'nome' => 'Laboratorio', 'tipo' => 'laboratorio']);
        $l = $this->lez($this->x, $this->ita, $this->s1, $this->a1);
        $storia = $this->lez($this->x, $this->sto, $this->s2, null, 'Altro');
        $referente = $this->actingAs($this->referente());
        $url = fn ($lezione) => "{$this->base()}/lezioni/{$lezione->id}/aula";

        $referente->patchJson($url($l), ['aula_id' => $laboratorio->id])->assertStatus(422)->assertJsonPath('errori.0', fn ($m) => str_contains($m, "serve un'aula di tipo 'dada_ita'"));
        $referente->patchJson($url($l), ['aula_id' => $this->a1->id])->assertStatus(422)->assertJsonPath('errori.0', fn ($m) => str_contains($m, 'è già in Italiano 1'));
        $referente->patchJson($url($storia), ['aula_id' => $this->a1->id])->assertStatus(422)->assertJsonPath('errori.0', fn ($m) => str_contains($m, "non richiede un'aula speciale"));
        $l->update(['bloccata' => true]);
        $referente->patchJson($url($l), ['aula_id' => $this->a2->id])->assertStatus(422)->assertJsonPath('errori.0', fn ($m) => str_contains($m, 'bloccata'));
        $this->assertSame($this->a1->id, $l->fresh()->aula_id);

        $referente->patchJson($url($l), [])->assertStatus(422);                       // aula_id obbligatoria
        $this->orario->update(['stato' => 'approvato']);
        $referente->patchJson($url($l), ['aula_id' => $this->a2->id])->assertStatus(422);   // non è più una bozza
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->patchJson($url($l), ['aula_id' => $this->a2->id])->assertForbidden();
    }

    public function test_un_aula_piena_e_un_conflitto_ammesso_solo_con_la_modalita_provvisoria(): void
    {
        $l = $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->y, $this->ita, $this->s1, $this->a2, 'Due');      // a2 occupata alla 1ª ora da 1ªB
        $referente = $this->actingAs($this->referente());
        $url = "{$this->base()}/lezioni/{$l->id}/aula";

        $referente->patchJson($url, ['aula_id' => $this->a2->id])->assertStatus(422)->assertJsonPath('errori.0', fn ($m) => str_contains($m, 'già usata da 1ª B'));
        $this->assertSame($this->a1->id, $l->fresh()->aula_id);

        $risposta = $referente->patchJson($url, ['aula_id' => $this->a2->id, 'provvisorio' => true])->assertOk();
        $this->assertStringContainsString('Conflitto provvisorio', $risposta->json('avvisi.0'));
        $this->assertSame($this->a2->id, $l->fresh()->aula_id);
        $this->assertTrue(collect(app(ControlloOrario::class)->problemi($this->orario))->contains(fn ($p) => str_contains($p['testo'], 'Italiano 2') && str_contains($p['testo'], 'usata da 2 classi')));
    }

    public function test_spostando_su_un_altra_ora_si_puo_scegliere_l_aula_di_destinazione(): void
    {
        $l = $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->x, $this->sto, $this->s2, null, 'Altro');          // si scambierà con la lezione spostata
        $referente = $this->actingAs($this->referente());

        // 2ª ora: entrambe le aule libere; scelgo a2
        $referente->patchJson("{$this->base()}/lezioni/{$l->id}/sposta", ['slot_id' => $this->s2->id, 'aula_id' => $this->a2->id])->assertOk();
        $this->assertSame([$this->s2->id, $this->a2->id], [$l->fresh()->slot_id, $l->fresh()->aula_id]);

        // senza preferenza resta nell'aula di prima
        $referente->patchJson("{$this->base()}/lezioni/{$l->id}/sposta", ['slot_id' => $this->s1->id])->assertOk();
        $this->assertSame($this->a2->id, $l->fresh()->aula_id);

        // aula preferita occupata: se ne usa un'altra libera
        $this->lez($this->y, $this->ita, $this->s2, $this->a1, 'Occupa');
        $referente->patchJson("{$this->base()}/lezioni/{$l->id}/sposta", ['slot_id' => $this->s2->id, 'aula_id' => $this->a1->id])->assertOk();
        $this->assertSame($this->a2->id, $l->fresh()->aula_id);

        $referente->patchJson("{$this->base()}/lezioni/{$l->id}/sposta", ['slot_id' => $this->s1->id, 'aula_id' => 99999])->assertStatus(422);   // aula inesistente
    }

    public function test_le_destinazioni_del_tabellone_dicono_dove_si_puo_trascinare(): void
    {
        $l = $this->lez($this->x, $this->ita, $this->s1, $this->a1, 'Uno');
        $this->lez($this->x, $this->sto, $this->s2, null, 'Altro');
        $this->lez($this->y, $this->ita, $this->s2, $this->a2, 'Due');       // a2 occupata alla 2ª ora
        $referente = $this->actingAs($this->referente());

        $esiti = $referente->getJson("{$this->base()}/lezioni/{$l->id}/destinazioni-aule")->assertOk()->json();
        $chiave = fn (Aula $a, Slot $s) => $a->id.'-'.$s->id;

        $this->assertSame('ok', $esiti[$chiave($this->a1, $this->s1)]['stato']);                  // dove sta già
        $this->assertSame('ok', $esiti[$chiave($this->a2, $this->s1)]['stato']);                  // stessa ora, altra aula libera
        $this->assertSame('ok', $esiti[$chiave($this->a1, $this->s2)]['stato']);                  // altra ora, aula libera
        $this->assertSame('conflitto', $esiti[$chiave($this->a2, $this->s2)]['stato']);           // aula occupata da 1ªB
        $this->assertStringContainsString('occupata da 1ª B', $esiti[$chiave($this->a2, $this->s2)]['motivi'][0]);
        $this->assertSame('ok', $esiti[$chiave($this->a1, $this->s3)]['stato']);

        $l->update(['bloccata' => true]);
        $this->assertSame('vietato', $referente->getJson("{$this->base()}/lezioni/{$l->id}/destinazioni-aule")->json()[$chiave($this->a1, $this->s2)]['stato']);

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->getJson("{$this->base()}/lezioni/{$l->id}/destinazioni-aule")->assertForbidden();
    }

    public function test_per_una_materia_senza_aula_speciale_l_unica_riga_possibile_e_l_aula_della_classe(): void
    {
        $base = Aula::factory()->create(['sede_id' => $this->x->sede_id, 'nome' => 'Aula 1A', 'tipo' => 'classe']);
        $this->x->update(['aula_base_id' => $base->id]);
        $l = $this->lez($this->x, $this->sto, $this->s1, null);
        $this->lez($this->x, $this->sto, $this->s2, null, 'Altro');

        $esiti = $this->actingAs($this->referente())->getJson("{$this->base()}/lezioni/{$l->id}/destinazioni-aule")->assertOk()->json();
        $this->assertSame([$base->id.'-'.$this->s1->id, $base->id.'-'.$this->s2->id, $base->id.'-'.$this->s3->id], array_keys($esiti));
    }
}
