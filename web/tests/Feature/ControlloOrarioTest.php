<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Slot;
use App\Models\User;
use App\Services\Editor\ControlloOrario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlloOrarioTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classeA;

    private Classe $classeB;

    private Slot $slot1;

    private Slot $slot2;

    private Orario $orario;

    private Docente $rossi;

    private Docente $bianchi;

    private Cattedra $italianoA;

    private Cattedra $storiaA;

    private Cattedra $italianoB;

    private Cattedra $storiaB;

    /**
     * Due classi, due slot (lunedì 1ª e 2ª ora), due docenti che insegnano in entrambe:
     * 1ªA: slot1 Rossi (Italiano), slot2 Bianchi (Storia); 2ªB: slot1 Bianchi (Storia), slot2 Rossi (Italiano).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->classeA = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A']);
        $this->classeB = Classe::factory()->create(['anno_corso' => 2, 'sezione' => 'B']);
        $this->slot1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
        $this->slot2 = Slot::factory()->create(['giorno' => 1, 'ordine' => 2, 'inizio' => '08:50:00', 'fine' => '09:40:00']);
        foreach ([$this->classeA, $this->classeB] as $c) {
            $c->slotAttivi()->sync([$this->slot1->id, $this->slot2->id]);
        }
        $this->rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $this->bianchi = Docente::factory()->create(['cognome' => 'Bianchi', 'nome' => 'Luca']);
        $this->orario = Orario::factory()->create();

        $nuova = fn (Classe $c, Docente $d) => Cattedra::factory()->create(['classe_id' => $c->id, 'docente_id' => $d->id, 'ore' => 1]);
        $this->italianoA = $nuova($this->classeA, $this->rossi);
        $this->storiaA = $nuova($this->classeA, $this->bianchi);
        $this->italianoB = $nuova($this->classeB, $this->rossi);
        $this->storiaB = $nuova($this->classeB, $this->bianchi);
        $this->lez = [
            'A1' => $this->lezione($this->italianoA, $this->slot1), 'A2' => $this->lezione($this->storiaA, $this->slot2),
            'B1' => $this->lezione($this->storiaB, $this->slot1), 'B2' => $this->lezione($this->italianoB, $this->slot2),
        ];
    }

    public array $lez = [];

    private function lezione(Cattedra $cattedra, Slot $slot): Lezione
    {
        return Lezione::factory()->create(['orario_id' => $this->orario->id, 'cattedra_id' => $cattedra->id, 'slot_id' => $slot->id]);
    }

    private function testi(): array
    {
        return array_column(app(ControlloOrario::class)->problemi($this->orario), 'testo');
    }

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_un_orario_coerente_non_ha_problemi(): void
    {
        $this->assertSame([], $this->testi());
    }

    public function test_riconosce_docente_in_due_posti_indisponibilita_ore_diverse_e_ore_vuote(): void
    {
        // Rossi diventa in due posti: la lezione B2 (2ªB, slot2) va in slot1 dove Rossi è già in 1ªA
        $this->lez['B2']->update(['slot_id' => $this->slot1->id]);
        $testi = $this->testi();
        $this->assertTrue(collect($testi)->contains(fn ($t) => str_contains($t, 'Rossi Anna, lunedì, 1ª ora (08:00–08:50): è in due posti') && str_contains($t, '1ª A') && str_contains($t, '2ª B')));
        $this->assertTrue(collect($testi)->contains(fn ($t) => str_contains($t, '2ª B, lunedì, 1ª ora (08:00–08:50): due lezioni nello stesso momento')));
        $this->assertTrue(collect($testi)->contains(fn ($t) => str_contains($t, '2ª B, lunedì, 2ª ora (08:50–09:40): nessuna lezione')));

        // indisponibilità
        $this->bianchi->indisponibilita()->attach($this->slot2->id);
        $this->assertTrue(collect($this->testi())->contains(fn ($t) => str_contains($t, '1ª A, lunedì, 2ª ora') && str_contains($t, 'Bianchi Luca') && str_contains($t, 'non è disponibile')));

        // ore diverse dal quadro
        $this->italianoA->update(['ore' => 3]);
        $this->assertTrue(collect($this->testi())->contains(fn ($t) => str_contains($t, '1ª A:') && str_contains($t, 'ha 1 ore invece delle 3 previste')));

        // gli errori vengono prima degli avvisi
        $gravita = array_column(app(ControlloOrario::class)->problemi($this->orario), 'gravita');
        $this->assertSame(['avviso'], array_values(array_unique(array_slice($gravita, -1))));
    }

    public function test_la_compresenza_dichiarata_non_e_un_conflitto(): void
    {
        $this->lez['B2']->update(['slot_id' => $this->slot1->id]);
        $this->italianoA->update(['compresenza' => true]);
        $this->italianoB->update(['compresenza' => true]);
        $testi = $this->testi();
        $this->assertFalse(collect($testi)->contains(fn ($t) => str_contains($t, 'è in due posti')));
    }

    public function test_le_pagine_del_controllo_e_il_conteggio_nell_elenco(): void
    {
        $referente = $this->actingAs($this->referente());
        $referente->get("/orari/{$this->orario->id}/controllo")->assertOk()->assertSee('Nessun problema');
        $referente->get('/orari')->assertOk()->assertSee('Controllo: nessun problema');

        $this->lez['B2']->update(['slot_id' => $this->slot1->id]);
        $referente->get("/orari/{$this->orario->id}/controllo")->assertOk()->assertSee('è in due posti')->assertSee('Apri 2ª B', false);
        $referente->get('/orari')->assertSee('Controllo: 2 errori, 1 avviso');

        // la griglia della classe mostra il pannello e segna in rosso i riquadri coinvolti
        $html = $referente->get("/orari/{$this->orario->id}/classe/{$this->classeA->id}")->assertOk()->assertSee("Controllo dell'orario", false)->getContent();
        $this->assertSame(1, substr_count($html, 'aria-invalid="true"'));
        $this->assertStringContainsString('Conflitto: vedi il controllo', $html);

        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get("/orari/{$this->orario->id}/controllo")->assertForbidden();
    }

    public function test_le_destinazioni_dicono_dove_si_puo_spostare_una_lezione(): void
    {
        $referente = $this->actingAs($this->referente());
        $url = "/orari/{$this->orario->id}/lezioni/{$this->lez['A1']->id}/destinazioni";

        // A1 (Rossi) in slot2 scambiandosi con A2 (Bianchi): Rossi è già in 2ªB slot2 → conflitto (solo provvisorio)
        $esiti = $referente->getJson($url)->assertOk()->json();
        $this->assertSame('conflitto', $esiti[$this->slot2->id]['stato']);
        $this->assertStringContainsString('2ª B', $esiti[$this->slot2->id]['motivi'][0]);
        $this->assertArrayNotHasKey($this->slot1->id, $esiti); // lo slot attuale non è una destinazione

        // se Rossi e Bianchi fossero liberi lo scambio sarebbe possibile: libera slot2 di 2ªB e slot1 di 2ªB
        $this->lez['B2']->delete();
        $this->lez['B1']->delete();
        $this->assertSame('ok', $referente->getJson($url)->json()[$this->slot2->id]['stato']);

        // una lezione bloccata o uno slot fuori scansione sono vietati
        $this->lez['A2']->update(['bloccata' => true]);
        $this->assertSame('vietato', $referente->getJson($url)->json()[$this->slot2->id]['stato']);
        $this->lez['A2']->update(['bloccata' => false]);
        $this->classeA->slotAttivi()->sync([$this->slot1->id]);
        $this->assertArrayNotHasKey($this->slot2->id, $referente->getJson($url)->json());

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->getJson($url)->assertForbidden();
    }

    public function test_conflitti_provvisori_permettono_scambi_tra_classi_da_completare_in_piu_passaggi(): void
    {
        $referente = $this->actingAs($this->referente());
        $base = "/orari/{$this->orario->id}/lezioni";

        // Senza modalità provvisoria lo scambio in 1ªA viene rifiutato (Rossi è già in 2ªB alla 2ª ora)
        $referente->patchJson("$base/{$this->lez['A1']->id}/sposta", ['slot_id' => $this->slot2->id])->assertStatus(422);
        $this->assertSame($this->slot1->id, $this->lez['A1']->fresh()->slot_id);

        // In modalità provvisoria si esegue: il conflitto è segnalato e resta nel Controllo
        $risposta = $referente->patchJson("$base/{$this->lez['A1']->id}/sposta", ['slot_id' => $this->slot2->id, 'provvisorio' => true])->assertOk();
        $this->assertStringContainsString('Conflitto provvisorio', $risposta->json('avvisi.0'));
        $this->assertSame($this->slot2->id, $this->lez['A1']->fresh()->slot_id);
        $this->assertTrue(collect($this->testi())->contains(fn ($t) => str_contains($t, 'Rossi Anna') && str_contains($t, 'è in due posti')));

        // Secondo passaggio, nell'altra classe: scambia B1 e B2 → Rossi libero alla 2ª ora, conflitto risolto da solo
        $referente->patchJson("$base/{$this->lez['B1']->id}/sposta", ['slot_id' => $this->slot2->id, 'provvisorio' => true])->assertOk();
        $this->assertSame([], $this->testi());

        // lo slot fuori scansione resta vietato anche in modalità provvisoria
        $this->classeA->slotAttivi()->sync([$this->slot2->id]);
        $referente->patchJson("$base/{$this->lez['A1']->id}/sposta", ['slot_id' => $this->slot1->id, 'provvisorio' => true])->assertStatus(422);   // A1 sta in slot2, slot1 non è più della classe

        // e annullare riporta indietro anche i passaggi provvisori
        $referente->post("/orari/{$this->orario->id}/annulla-ultima");
        $this->assertTrue(collect($this->testi())->contains(fn ($t) => str_contains($t, 'è in due posti')));
    }

    public function test_rilasciare_una_lezione_dove_gia_si_trova_non_e_un_errore_ne_una_modifica(): void
    {
        $risposta = $this->actingAs($this->referente())->patchJson("/orari/{$this->orario->id}/lezioni/{$this->lez['A1']->id}/sposta", ['slot_id' => $this->slot1->id]);

        $risposta->assertOk()->assertJsonPath('ok', true)->assertJsonPath('errori', [])->assertJsonPath('avvisi', []);
        $this->assertSame($this->slot1->id, $this->lez['A1']->fresh()->slot_id);
        $this->assertDatabaseCount('avvisi_orario', 0);                                           // niente nel registro
        $this->assertDatabaseMissing('modifiche_orario', ['lezione_id' => $this->lez['A1']->id]);  // niente da annullare
    }

    public function test_una_modifica_rifiutata_sta_nel_registro_mentre_il_controllo_resta_pulito(): void
    {
        $referente = $this->actingAs($this->referente());
        $referente->patchJson("/orari/{$this->orario->id}/lezioni/{$this->lez['A1']->id}/sposta", ['slot_id' => $this->slot2->id])->assertStatus(422);

        $html = $referente->get("/orari/{$this->orario->id}/classe/{$this->classeA->id}")->assertOk()->getContent();
        $this->assertStringContainsString('nessun problema per questa classe', $html);   // stato attuale: tutto a posto
        $this->assertStringContainsString('[modifica rifiutata]', $html);                 // ma il tentativo è nel registro
        $this->assertStringContainsString('nulla ha cambiato', str_replace('<strong>rifiutata</strong> non ha cambiato nulla', 'nulla ha cambiato', $html));
        $this->assertStringContainsString('Registro delle modifiche', $html);
    }

    public function test_il_controllo_compare_in_tutte_le_viste_filtrato_per_cio_che_si_guarda(): void
    {
        $aula = \App\Models\Aula::factory()->create(['sede_id' => $this->classeA->sede_id, 'nome' => 'Aula Prova', 'tipo' => 'classe', 'capienza' => 1]);
        $this->lez['A1']->update(['aula_id' => $aula->id]);
        $libera = \App\Models\Aula::factory()->create(['sede_id' => $this->classeA->sede_id, 'nome' => 'Aula Libera', 'tipo' => 'classe', 'capienza' => 1]);
        $referente = $this->actingAs($this->referente());
        $base = "/orari/{$this->orario->id}";

        // orario coerente: ovunque «nessun problema»
        $referente->get("$base/classe/{$this->classeA->id}")->assertSee("Controllo dell'orario", false)->assertSee('nessun problema per questa classe');
        $referente->get("$base/docente/{$this->rossi->id}")->assertSee("Controllo dell'orario", false)->assertSee('nessun problema per questo docente');
        $referente->get("$base/aula/{$aula->id}")->assertSee("Controllo dell'orario", false)->assertSee('nessun problema per questa aula');
        $referente->get("$base/tabellone?per=classe")->assertSee("Controllo dell'orario", false)->assertSee("nessun problema per tutto l'orario");

        // Rossi finisce in due posti: lo vedono la sua vista, l'aula coinvolta e il tabellone, non chi non c'entra
        $this->lez['B2']->update(['slot_id' => $this->slot1->id]);
        $this->assertStringContainsString('è in due posti', $referente->get("$base/docente/{$this->rossi->id}")->assertOk()->getContent());
        $this->assertStringContainsString('1 errore', $referente->get("$base/docente/{$this->bianchi->id}")->getContent());   // solo «due lezioni nello stesso momento» in 2ªB
        $this->assertStringContainsString('è in due posti', $referente->get("$base/aula/{$aula->id}")->getContent());
        $this->assertStringContainsString('nessun problema per questa aula', $referente->get("$base/aula/{$libera->id}")->getContent());
        $tabellone = $referente->get("$base/tabellone?per=classe")->getContent();
        $this->assertStringContainsString('è in due posti', $tabellone);
        $this->assertStringContainsString('Apri 2ª B', str_replace('&ordf;', 'ª', $tabellone));
    }

    public function test_la_docente_clil_conta_come_presente_nelle_lezioni_in_compresenza(): void
    {
        $clil = Docente::factory()->create(['cognome' => 'Smith', 'nome' => 'Emma']);
        // Smith è in compresenza con Rossi in 1ªA (slot1) e con Bianchi in 2ªB (slot1): è in due posti
        $this->italianoA->update(['docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $this->storiaB->update(['docente_clil_id' => $clil->id, 'ore_clil' => 1]);
        $this->lez['A1']->update(['con_clil' => true]);
        $this->lez['B1']->update(['con_clil' => true]);

        $testi = $this->testi();
        $this->assertTrue(collect($testi)->contains(fn ($t) => str_contains($t, 'Emma') && str_contains($t, 'è in due posti')));

        // senza il segno sulla lezione il docente CLIL non occupa lo slot
        $this->lez['B1']->update(['con_clil' => false]);
        $this->assertFalse(collect($this->testi())->contains(fn ($t) => str_contains($t, 'Emma')));
    }
}
