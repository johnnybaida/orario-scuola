<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AnagraficheTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_crea_una_sede(): void
    {
        $response = $this->actingAs($this->referente())->post('/sedi', [
            'nome' => 'Plesso Centrale',
            'indirizzo' => 'Via Roma 1',
        ]);

        $response->assertRedirect(route('sedi.index'));
        $this->assertDatabaseHas('sedi', ['nome' => 'Plesso Centrale']);
    }

    public function test_crea_unaula_legata_a_una_sede(): void
    {
        $sede = Sede::factory()->create();

        $response = $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id,
            'nome' => 'Palestra',
            'tipo' => 'palestra',
            'capienza' => 2,
        ]);

        $response->assertRedirect(route('aule.index'));
        $this->assertDatabaseHas('aule', ['nome' => 'Palestra', 'sede_id' => $sede->id]);
    }

    public function test_aula_dada_scelta_dalla_select_collega_la_disciplina_e_la_modale_non_ha_il_layout(): void
    {
        $sede = Sede::factory()->create();
        $disciplina = Disciplina::factory()->create(['codice' => 'ITA']);

        $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id, 'nome' => 'Aula Italiano', 'tipo' => "dada:{$disciplina->id}", 'capienza' => 1,
        ])->assertRedirect(route('aule.index'));

        $this->assertDatabaseHas('aule', ['tipo' => 'dada_ita']);
        $this->assertSame('dada_ita', $disciplina->fresh()->tipo_aula_richiesto);

        $this->actingAs($this->referente())->get('/aule/create', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertDontSee('<html', false)->assertSee('DADA · '.$disciplina->nome);
    }

    public function test_crea_unaula_dada_con_tipo_libero_e_la_collega_a_una_disciplina(): void
    {
        $sede = Sede::factory()->create();

        $rispostaAula = $this->actingAs($this->referente())->post('/aule', [
            'sede_id' => $sede->id,
            'nome' => 'Aula Italiano',
            'tipo' => 'dada_italiano',
            'capienza' => 1,
        ]);
        $rispostaAula->assertRedirect(route('aule.index'));
        $this->assertDatabaseHas('aule', ['tipo' => 'dada_italiano']);

        $disciplina = Disciplina::factory()->create();
        $rispostaDisciplina = $this->actingAs($this->referente())
            ->put("/discipline/{$disciplina->id}", [
                'codice' => $disciplina->codice,
                'nome' => $disciplina->nome,
                'tipo_aula_richiesto' => 'dada_italiano',
            ]);

        $rispostaDisciplina->assertRedirect(route('discipline.index'));
        $this->assertDatabaseHas('discipline', ['id' => $disciplina->id, 'tipo_aula_richiesto' => 'dada_italiano']);
    }

    public function test_il_quadro_orario_salva_le_righe_dal_form_e_ricalcola_il_totale(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $italiano = Disciplina::factory()->create();
        $storia = Disciplina::factory()->create();
        $vecchia = $quadro->righe()->create(['disciplina_id' => $storia->id, 'ore_settimanali' => 2]);

        $this->actingAs($this->referente())->put("/quadri-orari/{$quadro->id}", [
            'nome' => $quadro->nome, 'sezioni_extra' => 1,
            'righe' => [['disciplina_id' => $italiano->id, 'ore_settimanali' => 6]],
        ])->assertRedirect();

        $this->assertDatabaseHas('quadro_orario_righe', ['quadro_orario_id' => $quadro->id, 'disciplina_id' => $italiano->id, 'ore_settimanali' => 6]);
        $this->assertModelMissing($vecchia);
        $this->assertSame(6, $quadro->fresh()->ore_totali);
    }

    public function test_il_form_docente_salva_indisponibilita_e_cattedre_insieme(): void
    {
        $docente = Docente::factory()->create();
        $slot = Slot::factory()->create();
        $classe = Classe::factory()->create();
        $disciplina = Disciplina::factory()->create();

        $this->actingAs($this->referente())->put("/docenti/{$docente->id}", [
            'nome' => $docente->nome, 'cognome' => $docente->cognome, 'tipo_contratto' => $docente->tipo_contratto,
            'tipo_posto' => $docente->tipo_posto, 'regime' => $docente->regime, 'ore_dovute' => 18,
            'sezioni_extra' => 1, 'cattedre_inviate' => 1, 'slot_ids' => [$slot->id],
            'cattedre' => [['classe_id' => $classe->id, 'disciplina_id' => $disciplina->id, 'ore' => 4, 'compresenza' => '1']],
        ])->assertRedirect();

        $this->assertTrue($docente->indisponibilita()->whereKey($slot->id)->exists());
        $this->assertDatabaseHas('cattedre', ['docente_id' => $docente->id, 'classe_id' => $classe->id, 'ore' => 4, 'compresenza' => true]);
    }

    public function test_crea_un_docente_con_classi_di_concorso_e_sedi(): void
    {
        $sede = Sede::factory()->create();

        $response = $this->actingAs($this->referente())->post('/docenti', [
            'nome' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'mario.rossi@scuola.test',
            'tipo_contratto' => 'tempo_indeterminato',
            'tipo_posto' => 'comune',
            'regime' => 'tempo_pieno',
            'ore_dovute' => 18,
            'classi_concorso' => ['A022', 'A028'],
            'sedi' => [$sede->id],
        ]);

        $response->assertRedirect();
        $docente = Docente::query()->where('email', 'mario.rossi@scuola.test')->firstOrFail();
        $this->assertCount(2, $docente->classiConcorso);
        $this->assertTrue($docente->sedi->contains($sede));
    }

    public function test_importa_docenti_da_csv(): void
    {
        $csv = "nome,cognome,email\nAnna,Bianchi,anna.bianchi@scuola.test\nLuca,Verdi,luca.verdi@scuola.test\n";
        $file = UploadedFile::fake()->createWithContent('docenti.csv', $csv);

        $response = $this->actingAs($this->referente())->post('/docenti-import', ['file' => $file]);

        $response->assertRedirect(route('docenti.index'));
        $this->assertDatabaseCount('docenti', 2);
        $this->assertDatabaseHas('docenti', ['email' => 'anna.bianchi@scuola.test']);
    }

    public function test_crea_una_classe_con_slot_mattutini_di_default(): void
    {
        $sede = Sede::factory()->create();
        $quadro = QuadroOrario::factory()->create();
        $this->seedSlotMattutini();

        $response = $this->actingAs($this->referente())->post('/classi', [
            'anno_corso' => 1,
            'sezione' => 'A',
            'sede_id' => $sede->id,
            'quadro_orario_id' => $quadro->id,
            'tempo_scuola' => 'normale',
            'n_alunni' => 20,
        ]);

        $response->assertRedirect();
        $classe = Classe::query()->where('sezione', 'A')->firstOrFail();
        $this->assertCount(30, $classe->slotAttivi);
    }

    public function test_crea_una_cattedra(): void
    {
        $docente = Docente::factory()->create();
        $classe = Classe::factory()->create();
        $disciplina = Disciplina::factory()->create();

        $response = $this->actingAs($this->referente())->post('/cattedre', [
            'docente_id' => $docente->id,
            'classe_id' => $classe->id,
            'disciplina_id' => $disciplina->id,
            'ore' => 6,
        ]);

        $response->assertRedirect(route('cattedre.index'));
        $this->assertDatabaseHas('cattedre', [
            'docente_id' => $docente->id,
            'classe_id' => $classe->id,
            'disciplina_id' => $disciplina->id,
            'ore' => 6,
        ]);
    }

    public function test_la_segreteria_non_puo_gestire_lanagrafica_generale(): void
    {
        $segreteria = User::factory()->create(['ruolo' => 'segreteria']);

        $response = $this->actingAs($segreteria)->post('/sedi', ['nome' => 'Plesso Vietato']);

        $response->assertForbidden();
    }

    public function test_la_segreteria_puo_gestire_i_docenti(): void
    {
        $segreteria = User::factory()->create(['ruolo' => 'segreteria']);

        $response = $this->actingAs($segreteria)->post('/docenti', [
            'nome' => 'Giulia',
            'cognome' => 'Neri',
            'tipo_contratto' => 'tempo_indeterminato',
            'tipo_posto' => 'comune',
            'regime' => 'tempo_pieno',
            'ore_dovute' => 18,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('docenti', ['cognome' => 'Neri']);
    }

    private function seedSlotMattutini(): void
    {
        for ($giorno = 1; $giorno <= 5; $giorno++) {
            for ($ordine = 1; $ordine <= 6; $ordine++) {
                Slot::query()->create([
                    'giorno' => $giorno,
                    'ordine' => $ordine,
                    'inizio' => '08:00:00',
                    'fine' => '08:50:00',
                    'intervallo_dopo' => false,
                ]);
            }
        }
    }

    public function test_elimina_un_orario_e_lo_registra_nellaudit_log(): void
    {
        $orario = \App\Models\Orario::factory()->create();

        $this->actingAs($this->referente())->delete("/orari/{$orario->id}")->assertRedirect(route('orari.index'));

        $this->assertModelMissing($orario);
        $this->assertDatabaseHas('audit_log', ['entita' => 'Orario', 'entita_id' => $orario->id, 'azione' => 'eliminazione']);
    }

    public function test_non_elimina_un_quadro_orario_usato_da_classi(): void
    {
        $classe = Classe::factory()->create();
        $libero = QuadroOrario::factory()->create();

        $this->actingAs($this->referente());
        $this->delete("/quadri-orari/{$classe->quadro_orario_id}")->assertStatus(422);
        $this->assertDatabaseHas('quadri_orari', ['id' => $classe->quadro_orario_id]);

        $this->delete("/quadri-orari/{$libero->id}")->assertRedirect(route('quadri-orari.index'));
        $this->assertModelMissing($libero);
    }

    public function test_i_rientri_pomeridiani_si_scelgono_per_giorno_senza_toccare_la_mattina(): void
    {
        $classe = Classe::factory()->create();
        $mattina = Slot::factory()->create(['giorno' => 2, 'ordine' => 1]);
        $martedi = Slot::factory()->create(['giorno' => 2, 'ordine' => 7]);
        $giovedi = Slot::factory()->create(['giorno' => 4, 'ordine' => 7]);
        $classe->slotAttivi()->sync([$mattina->id, $giovedi->id]);

        $dati = [
            'anno_corso' => $classe->anno_corso, 'sezione' => $classe->sezione, 'sede_id' => $classe->sede_id,
            'quadro_orario_id' => $classe->quadro_orario_id, 'tempo_scuola' => 'prolungato', 'n_alunni' => 20,
        ];
        $this->actingAs($this->referente())->put("/classi/{$classe->id}", $dati + ['rientri' => [2]])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$mattina->id, $martedi->id],
            $classe->slotAttivi()->pluck('slot.id')->all(),
        );
    }

    public function test_le_pagine_di_modifica_si_aprono_con_righe_e_barra_di_salvataggio(): void
    {
        $docente = Docente::factory()->create();
        $quadro = QuadroOrario::factory()->create();
        $quadro->righe()->create(['disciplina_id' => Disciplina::factory()->create()->id, 'ore_settimanali' => 3]);
        $utente = $this->actingAs($this->referente());

        $utente->get("/docenti/{$docente->id}/edit")->assertOk()->assertSee('data-totale="cattedre"', false)->assertSee('Salva');
        $utente->get("/quadri-orari/{$quadro->id}/edit")->assertOk()->assertSee('data-totale="quadro"', false)->assertSee('data-univoca', false)->assertSee('Tutte le discipline sono già nel quadro orario.')->assertSee('Salva');
        // Nella modale il Salva/Annulla è quello della modale, non la barra della pagina.
        $utente->get("/quadri-orari/{$quadro->id}/edit", ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertDontSee('fixed bottom-0', false);
    }

    public function test_la_migrazione_porta_tutti_i_giorni_fino_alla_nona_ora(): void
    {
        Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        Slot::factory()->create(['giorno' => 2, 'ordine' => 7, 'inizio' => '14:00:00', 'fine' => '14:50:00']);

        (require database_path('migrations/2026_10_03_160000_add_ore_pomeridiane_a_tutti_i_giorni.php'))->up();

        foreach ([1, 2] as $giorno) {
            $this->assertSame([7, 8, 9], Slot::query()->where('giorno', $giorno)->where('ordine', '>', 6)->orderBy('ordine')->pluck('ordine')->all());
        }
        $this->assertSame('14:00:00', Slot::query()->where(['giorno' => 1, 'ordine' => 7])->value('inizio'));
        $this->assertSame(1, Slot::query()->where(['giorno' => 1, 'ordine' => 1])->count());
    }

    public function test_docenti_e_classi_si_creano_e_modificano_su_pagina_non_in_modale(): void
    {
        $this->actingAs($this->referente());

        foreach (['/docenti', '/classi'] as $indice) {
            $this->get($indice)->assertOk()->assertDontSee('data-modale', false);
        }
        $this->get('/docenti/create')->assertOk()->assertSee('fixed bottom-0', false);
        $this->get('/classi/create')->assertOk()->assertSee('fixed bottom-0', false);
    }

    public function test_i_controlli_condizionati_hanno_la_condizione_e_il_tooltip_che_spiega_come_attivarli(): void
    {
        $quadro = QuadroOrario::factory()->create();
        $utente = $this->actingAs($this->referente());

        $utente->get('/vincoli/create')->assertOk()
            ->assertSee('data-attiva-se="#ambito_livello=classe"', false)
            ->assertSee('Per scegliere le classi imposta Ambito = Classe.')
            ->assertSee('data-attiva-se="#severita=preferenziale"', false);
        $utente->get('/classi/create')->assertOk()->assertSee('data-attiva-se="#tempo_scuola=prolungato"', false);

        // Nessuna disciplina censita: "aggiungi" è disabilitato e spiega perché.
        $utente->get("/quadri-orari/{$quadro->id}/edit")->assertOk()->assertSee('Nessuna disciplina censita');
    }

    public function test_il_menu_porta_sia_alle_sedi_sia_alle_aule(): void
    {
        $this->actingAs($this->referente())->get('/dashboard')
            ->assertSee('href="'.route('sedi.index').'"', false)
            ->assertSee('href="'.route('aule.index').'"', false);
    }

    public function test_il_quadro_orario_si_crea_con_le_discipline_in_un_solo_salvataggio(): void
    {
        $italiano = Disciplina::factory()->create();
        $utente = $this->actingAs($this->referente());

        $utente->get('/quadri-orari/create')->assertOk()->assertSee('data-univoca', false);

        $utente->post('/quadri-orari', [
            'nome' => 'Tempo normale 30h', 'sezioni_extra' => 1,
            'righe' => [['disciplina_id' => $italiano->id, 'ore_settimanali' => 6]],
        ])->assertRedirect(route('quadri-orari.index'));

        $quadro = QuadroOrario::query()->where('nome', 'Tempo normale 30h')->firstOrFail();
        $this->assertSame(6, $quadro->ore_totali);
        $this->assertDatabaseHas('quadro_orario_righe', ['quadro_orario_id' => $quadro->id, 'disciplina_id' => $italiano->id]);
    }

    public function test_i_campi_obbligatori_sono_marcati_required_cosi_compare_lasterisco(): void
    {
        $utente = $this->actingAs($this->referente());

        $utente->get('/docenti/create')->assertOk()
            ->assertSee('name="nome" id="nome" value="" required', false)
            ->assertSee('name="tipo_contratto" id="tipo_contratto" required', false)
            ->assertSee('name="ore_dovute" id="ore_dovute" min="1" max="24" value="18" required', false)
            ->assertSee('campo obbligatorio'); // legenda nella barra di salvataggio
        $utente->get('/vincoli/create')->assertOk()
            ->assertSee('name="parametri[disciplina_id]" required', false)
            ->assertSee('data-attiva-se="#severita=preferenziale" data-richiesto', false);
    }

    public function test_le_tabelle_hanno_filtri_e_pulsante_nuovo_nella_barra_sopra_la_tabella(): void
    {
        $admin = $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));

        foreach (['/sedi', '/aule', '/discipline', '/quadri-orari', '/docenti', '/classi', '/cattedre', '/vincoli', '/generazioni', '/utenze'] as $pagina) {
            $risposta = $admin->get($pagina)->assertOk();
            $html = $risposta->getContent();
            // La barra della tabella compare dopo il titolo e prima della tabella, con il pulsante "Nuovo" dentro.
            $this->assertMatchesRegularExpression('/<h1[^>]*>.*?<\/h1>.*?class="mb-3 flex flex-wrap items-end justify-between.*?<table/s', $html, $pagina);
        }
    }

    public function test_gli_slot_nel_form_vincoli_usano_le_sigle_dei_giorni(): void
    {
        Slot::factory()->create(['giorno' => 2, 'ordine' => 3]);

        $this->actingAs($this->referente())->get('/vincoli/create')
            ->assertOk()->assertSee('MAR-3ª')->assertDontSee('G2-3ª');
    }

    public function test_le_classi_di_concorso_del_docente_mostrano_le_discipline_collegate(): void
    {
        Disciplina::factory()->create(['nome' => 'Italiano', 'classe_concorso' => 'A022']);
        Disciplina::factory()->create(['nome' => 'Geografia', 'classe_concorso' => 'A022']);
        Disciplina::factory()->create(['nome' => 'Matematica', 'classe_concorso' => 'A028']);

        $this->actingAs($this->referente())->get('/docenti/create')->assertOk()
            ->assertSee('A022')
            ->assertSee('Geografia, Italiano') // ordinate per nome
            ->assertSee('Matematica');
    }

    public function test_la_scansione_oraria_aggiorna_orari_e_ricreazioni_per_tutti_i_giorni(): void
    {
        foreach ([1, 2] as $giorno) {
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
            Slot::factory()->create(['giorno' => $giorno, 'ordine' => 2, 'inizio' => '08:50:00', 'fine' => '09:40:00']);
        }
        $utente = $this->actingAs($this->referente());

        $utente->get('/scansione-oraria')->assertOk()->assertSee('Ricreazione dopo');

        $utente->put('/scansione-oraria', ['ore' => [
            1 => ['inizio' => '08:00', 'fine' => '08:55', 'ricreazione' => '10'],
            2 => ['inizio' => '09:05', 'fine' => '10:00'],
        ]])->assertRedirect(route('scansione.index'));

        foreach ([1, 2] as $giorno) {
            $this->assertDatabaseHas('slot', ['giorno' => $giorno, 'ordine' => 1, 'fine' => '08:55:00', 'intervallo_dopo' => true]);
            $this->assertDatabaseHas('slot', ['giorno' => $giorno, 'ordine' => 2, 'inizio' => '09:05:00', 'intervallo_dopo' => false, 'ricreazione_minuti' => null]);
        }
        $this->assertDatabaseHas('audit_log', ['entita' => 'ScansioneOraria', 'azione' => 'modifica']);
    }

    public function test_la_scansione_oraria_rifiuta_orari_incoerenti(): void
    {
        Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00']);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 2, 'inizio' => '08:50:00', 'fine' => '09:40:00']);
        $utente = $this->actingAs($this->referente());

        // fine prima dell'inizio
        $utente->put('/scansione-oraria', ['ore' => [1 => ['inizio' => '09:00', 'fine' => '08:00'], 2 => ['inizio' => '09:10', 'fine' => '10:00']]])
            ->assertSessionHasErrors('ore.1.fine');
        // sovrapposizione con l'ora precedente
        $utente->put('/scansione-oraria', ['ore' => [1 => ['inizio' => '08:00', 'fine' => '09:00'], 2 => ['inizio' => '08:50', 'fine' => '10:00']]])
            ->assertSessionHasErrors('ore.2.inizio');
        // ricreazione senza pausa tra le due ore
        $utente->put('/scansione-oraria', ['ore' => [1 => ['inizio' => '08:00', 'fine' => '08:50', 'ricreazione' => '10'], 2 => ['inizio' => '08:50', 'fine' => '09:40']]])
            ->assertSessionHasErrors('ore.1.ricreazione');
        // ricreazione dopo l'ultima ora
        $utente->put('/scansione-oraria', ['ore' => [1 => ['inizio' => '08:00', 'fine' => '08:50'], 2 => ['inizio' => '09:00', 'fine' => '09:50', 'ricreazione' => '10']]])
            ->assertSessionHasErrors('ore.2.ricreazione');

        $this->assertDatabaseHas('slot', ['ordine' => 1, 'fine' => '08:50:00']); // niente è stato salvato
    }

    public function test_le_ricreazioni_hanno_durate_diverse_e_si_tolgono_svuotando_il_campo_senza_errori(): void
    {
        foreach (['08:00' => '08:50', '08:50' => '09:40', '09:55' => '10:45'] as $inizio => $fine) {
            static $ordine = 0;
            $ordine++;
            Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine, 'inizio' => $inizio.':00', 'fine' => $fine.':00']);
        }
        $utente = $this->actingAs($this->referente());
        $ore = fn (array $r) => ['ore' => [
            1 => ['inizio' => '08:00', 'fine' => '08:50', 'ricreazione' => $r[0]],
            2 => ['inizio' => '09:00', 'fine' => '09:50', 'ricreazione' => $r[1]],
            3 => ['inizio' => '10:05', 'fine' => '10:55'],
        ]];

        // 10' dopo la 1ª (08:50-09:00) e 15' dopo la 2ª (09:50-10:05): durate diverse
        $utente->put('/scansione-oraria', $ore(['10', '15']))->assertRedirect(route('scansione.index'));
        $this->assertDatabaseHas('slot', ['ordine' => 1, 'ricreazione_minuti' => 10, 'intervallo_dopo' => true]);
        $this->assertDatabaseHas('slot', ['ordine' => 2, 'ricreazione_minuti' => 15, 'intervallo_dopo' => true]);
        $utente->get('/scansione-oraria')->assertSee('08:50–09:00', false)->assertSee('09:50–10:05', false);

        // si toglie svuotando il campo (o con 0), nessun errore anche se resta spazio tra le ore
        $utente->put('/scansione-oraria', $ore(['', '0']))->assertRedirect(route('scansione.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('slot', ['intervallo_dopo' => true]);
        $this->assertDatabaseMissing('slot', ['ricreazione_minuti' => 10]);

        // la ricreazione non può invadere l'ora successiva: 15' dopo la 1ª (fino 09:05) ma la 2ª inizia 09:00
        $utente->put('/scansione-oraria', $ore(['15', '']))->assertSessionHasErrors('ore.1.ricreazione');
    }

    public function test_solo_chi_gestisce_lanagrafica_modifica_la_scansione_oraria_ma_tutti_i_ruoli_operativi_la_vedono(): void
    {
        Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get('/scansione-oraria')->assertOk();
        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->put('/scansione-oraria', ['ore' => [1 => ['inizio' => '08:00', 'fine' => '08:50']]])->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get('/scansione-oraria')->assertForbidden();
    }
}
