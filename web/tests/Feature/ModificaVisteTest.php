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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le viste con i riquadri delle lezioni (docente, aula, tabellone per classe e per aula) si modificano trascinando. */
class ModificaVisteTest extends TestCase
{
    use RefreshDatabase;

    private Orario $orario;

    private Classe $x;

    private Classe $y;

    private Slot $s1;

    private Slot $s2;

    private Slot $s3;

    private Docente $rossi;

    private Aula $aula;

    private Lezione $rx1;

    private Lezione $bx2;

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Sede::factory()->create();
        $this->orario = Orario::factory()->create();
        foreach ([1, 2, 3] as $o) {
            $this->{'s'.$o} = Slot::factory()->create(['giorno' => 1, 'ordine' => $o]);
        }
        $this->aula = Aula::factory()->create(['sede_id' => $sede->id, 'nome' => 'Aula Italiano 1', 'tipo' => 'dada_ita', 'capienza' => 1]);
        $this->x = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A', 'sede_id' => $sede->id, 'aula_base_id' => null]);
        $this->y = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'B', 'sede_id' => $sede->id, 'aula_base_id' => null]);
        foreach ([$this->x, $this->y] as $c) {
            $c->slotAttivi()->sync([$this->s1->id, $this->s2->id, $this->s3->id]);
        }
        $ita = Disciplina::factory()->create(['codice' => 'ITA', 'nome' => 'Italiano', 'tipo_aula_richiesto' => 'dada_ita']);
        $sto = Disciplina::factory()->create(['codice' => 'STO', 'nome' => 'Storia']);
        $this->rossi = Docente::factory()->create(['cognome' => 'Rossi']);
        $bianchi = Docente::factory()->create(['cognome' => 'Bianchi']);
        $cat = fn (Classe $c, Disciplina $d, Docente $doc) => Cattedra::factory()->create(['classe_id' => $c->id, 'disciplina_id' => $d->id, 'docente_id' => $doc->id, 'ore' => 1]);
        $this->rx1 = $this->lez($cat($this->x, $ita, $this->rossi), $this->s1, $this->aula);      // Rossi, 1ªA, ora 1, in aula
        $this->bx2 = $this->lez($cat($this->x, $sto, $bianchi), $this->s2);                       // Bianchi, 1ªA, ora 2
        $this->lez($cat($this->y, $sto, $bianchi), $this->s3);                                    // Bianchi, 1ªB, ora 3: allunga la griglia fino alla 3ª ora
    }

    private function lez(Cattedra $c, Slot $slot, ?Aula $aula = null): Lezione
    {
        return Lezione::factory()->create(['orario_id' => $this->orario->id, 'cattedra_id' => $c->id, 'slot_id' => $slot->id, 'aula_id' => $aula?->id]);
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    private function base(): string
    {
        return "/orari/{$this->orario->id}";
    }

    public function test_la_vista_docente_si_modifica_trascinando_e_mostra_la_barra_degli_strumenti(): void
    {
        $html = $this->actingAs($this->referente())->get("{$this->base()}/docente/{$this->rossi->id}")->assertOk()->getContent();

        $this->assertStringContainsString('data-editabile="1"', $html);
        $this->assertStringContainsString('data-modo="slot"', $html);
        $this->assertSame(1, substr_count($html, 'draggable="true"'));                    // l'unica lezione di Rossi
        foreach ([$this->s1, $this->s2, $this->s3] as $slot) {
            $this->assertMatchesRegularExpression('/<td[^>]*data-slot-id="'.$slot->id.'"/', $html);   // tutte le ore in uso: anche quelle libere sono destinazioni
        }
        $this->assertStringContainsString('id="conflitti-provvisori"', $html);
        $this->assertStringContainsString('id="form-annulla"', $html);
        $this->assertStringContainsString('id="form-ripeti"', $html);
    }

    public function test_si_sposta_una_lezione_dalla_vista_docente_e_si_annulla(): void
    {
        $referente = $this->actingAs($this->referente());

        // l'esito dello spostamento è lo stesso della griglia della classe: scambia con la lezione che la classe ha in quell'ora
        $risposta = $referente->getJson("{$this->base()}/lezioni/{$this->rx1->id}/destinazioni")->assertOk()->json();
        $this->assertSame('ok', $risposta[$this->s2->id]['stato']);      // Rossi libero alla 2ª e Bianchi libero alla 1ª: lo scambio si può fare

        $referente->patchJson("{$this->base()}/lezioni/{$this->rx1->id}/sposta", ['slot_id' => $this->s2->id])->assertOk();
        $this->assertSame([$this->s2->id, $this->s1->id], [$this->rx1->fresh()->slot_id, $this->bx2->fresh()->slot_id]);

        // dalla vista docente si vede il risultato e si può annullare
        $referente->get("{$this->base()}/docente/{$this->rossi->id}")->assertOk()->assertSee('id="form-annulla"', false);
        $referente->post("{$this->base()}/annulla-ultima")->assertSessionHas('successo');
        $this->assertSame($this->s1->id, $this->rx1->fresh()->slot_id);
    }

    public function test_la_vista_aula_si_modifica_e_sposta_nella_stessa_aula(): void
    {
        $referente = $this->actingAs($this->referente());
        $html = $referente->get("{$this->base()}/aula/{$this->aula->id}")->assertOk()->getContent();

        $this->assertStringContainsString('data-modo="aula"', $html);
        $this->assertStringContainsString('data-editabile="1"', $html);
        $this->assertSame(1, substr_count($html, 'draggable="true"'));
        $this->assertStringContainsString('data-aula-id="'.$this->aula->id.'"', $html);
        $this->assertStringContainsString('id="conflitti-provvisori"', $html);
        $this->assertStringContainsString('<a href="'.route('orari.classe', [$this->orario, $this->x]).'"', $html);   // il nome della classe resta un link

        // l'aula è libera alle 2ª e 3ª ora: la lezione ci si sposta e resta nell'aula scelta
        $esiti = $referente->getJson("{$this->base()}/lezioni/{$this->rx1->id}/destinazioni-aule")->json();
        $this->assertSame('ok', $esiti[$this->aula->id.'-'.$this->s3->id]['stato']);
        $referente->patchJson("{$this->base()}/lezioni/{$this->rx1->id}/sposta", ['slot_id' => $this->s3->id, 'aula_id' => $this->aula->id])->assertOk();
        $this->assertSame([$this->s3->id, $this->aula->id], [$this->rx1->fresh()->slot_id, $this->rx1->fresh()->aula_id]);
    }

    public function test_il_tabellone_per_classe_e_per_aula_hanno_la_barra_degli_strumenti_e_il_registro(): void
    {
        $referente = $this->actingAs($this->referente());
        $referente->patchJson("{$this->base()}/lezioni/{$this->rx1->id}/sposta", ['slot_id' => $this->rx1->slot_id]);   // no-op
        $this->lez(Cattedra::factory()->create(['classe_id' => $this->y->id]), $this->s1)->update(['bloccata' => true]);
        $referente->patchJson("{$this->base()}/lezioni/{$this->rx1->id}/aula", ['aula_id' => 99999]);                       // errore registrato nel registro

        foreach (['classe', 'aula'] as $per) {
            $html = $referente->get("{$this->base()}/tabellone?per=$per")->assertOk()->getContent();
            $this->assertStringContainsString('id="conflitti-provvisori"', $html, $per);
            $this->assertStringContainsString('id="form-annulla"', $html, $per);
            $this->assertStringContainsString('data-editabile="1"', $html, $per);
        }
    }

    public function test_le_viste_sono_in_sola_lettura_senza_permesso_o_fuori_dalla_bozza(): void
    {
        $viste = [
            "{$this->base()}/docente/{$this->rossi->id}", "{$this->base()}/aula/{$this->aula->id}",
            "{$this->base()}/tabellone?per=classe", "{$this->base()}/tabellone?per=aula",
        ];

        foreach ([['ds', 'bozza'], ['referente_orario', 'approvato']] as [$ruolo, $stato]) {
            $this->orario->update(['stato' => $stato]);
            $utente = $this->actingAs(User::factory()->create(['ruolo' => $ruolo]));
            foreach ($viste as $url) {
                $html = $utente->get($url)->assertOk()->getContent();
                $this->assertStringContainsString('data-editabile="0"', $html, "$ruolo $stato $url");
                $this->assertSame(0, substr_count($html, 'draggable="true"'), "$ruolo $stato $url");
                $this->assertStringNotContainsString('id="conflitti-provvisori"', $html, "$ruolo $stato $url");
                $this->assertStringNotContainsString('id="form-annulla"', $html, "$ruolo $stato $url");
            }
        }
    }

    public function test_le_lezioni_bloccate_non_si_trascinano_in_nessuna_vista(): void
    {
        $this->rx1->update(['bloccata' => true]);
        $referente = $this->actingAs($this->referente());

        $this->assertSame(0, substr_count($referente->get("{$this->base()}/docente/{$this->rossi->id}")->getContent(), 'draggable="true"'));
        $this->assertSame(0, substr_count($referente->get("{$this->base()}/aula/{$this->aula->id}")->getContent(), 'draggable="true"'));
    }
}
