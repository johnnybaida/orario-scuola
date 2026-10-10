<?php

namespace Tests\Feature;

use App\Models\AssegnazioneSostegno;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocenteOreTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_elenco_mostra_le_ore_assegnate_su_quelle_dovute_con_il_sostegno(): void
    {
        $docente = Docente::factory()->create(['cognome' => 'Rossi', 'ore_dovute' => 18]);
        Cattedra::factory()->create(['docente_id' => $docente->id, 'ore' => 6]);
        Cattedra::factory()->create(['docente_id' => $docente->id, 'ore' => 4]);
        AssegnazioneSostegno::factory()->create(['docente_id' => $docente->id, 'classe_id' => Classe::factory(), 'ore' => 3]);
        $completo = Docente::factory()->create(['cognome' => 'Bianchi', 'ore_dovute' => 18]);
        Cattedra::factory()->create(['docente_id' => $completo->id, 'ore' => 18]);
        Docente::factory()->create(['cognome' => 'Verdi', 'ore_dovute' => 9]);   // senza ore

        $r = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/docenti')->assertOk()
            ->assertSee('Assegnate / dovute')->assertDontSee('Ore dovute</th>', false);

        $r->assertSee('13 / 18')->assertSee('18 / 18')->assertSee('0 / 9');   // 6 + 4 di cattedra + 3 di sostegno
    }

    public function test_la_scheda_del_docente_elenca_sostegno_e_compresenze_clil_in_sola_lettura_e_le_somma_al_totale(): void
    {
        $classe = \App\Models\Classe::factory()->create(['anno_corso' => 3, 'sezione' => 'A']);
        $altra = \App\Models\Classe::factory()->create(['anno_corso' => 3, 'sezione' => 'C']);
        $docente = \App\Models\Docente::factory()->create(['cognome' => 'Tabuso', 'tipo_posto' => 'sostegno']);
        $classe->assegnazioniSostegno()->create(['docente_id' => $docente->id, 'ore' => 18]);
        $altra->assegnazioniSostegno()->create(['docente_id' => $docente->id, 'ore' => 9]);
        $titolare = \App\Models\Docente::factory()->create(['cognome' => 'Rossi']);
        \App\Models\Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $titolare->id, 'docente_clil_id' => $docente->id, 'ore_clil' => 2]);

        $pagina = $this->actingAs(\App\Models\User::factory()->create(['ruolo' => 'referente_orario']))->get("/docenti/{$docente->id}/edit")->assertOk();

        $pagina->assertSee('Sostegno e compresenze CLIL')->assertSee('3ª A')->assertSee('18 h')->assertSee('3ª C')->assertSee('9 h')
            ->assertSee('Totale sostegno: 27 h', false)->assertSee('Totale CLIL: 2 h', false)->assertSee('con Rossi', false)
            ->assertSee('<input type="hidden" data-somma="cattedre" value="27">', false)->assertSee('<input type="hidden" data-somma="cattedre" value="2">', false);

        // un docente senza sostegno né CLIL non ha la sezione
        $libero = \App\Models\Docente::factory()->create();
        $this->get("/docenti/{$libero->id}/edit")->assertOk()->assertDontSee('Sostegno e compresenze CLIL');
    }

    public function test_l_elenco_docenti_si_filtra_per_ore_non_corrette_e_mostra_chi_ha_assistenze_alle_pause(): void
    {
        $classe = \App\Models\Classe::factory()->create();
        $disc = \App\Models\Disciplina::factory()->create();
        $nuovo = fn (string $cognome, int $dovute, int $ore) => tap(\App\Models\Docente::factory()->create(['cognome' => $cognome, 'ore_dovute' => $dovute]),
            fn ($d) => $ore > 0 && \App\Models\Cattedra::factory()->create(['docente_id' => $d->id, 'classe_id' => $classe->id, 'disciplina_id' => \App\Models\Disciplina::factory()->create()->id, 'ore' => $ore]));
        $giusto = $nuovo('Giusti', 18, 18);
        $inPiu = $nuovo('Eccessi', 18, 20);
        $mancante = $nuovo('Mancanti', 18, 10);
        \App\Models\Slot::query()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'intervallo_dopo' => true, 'ricreazione_minuti' => 60, 'ricreazione_nome' => 'Mensa']);
        $giusto->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);   // 60' = 1 ora in più: Giusti passa a 19 su 18
        $utente = $this->actingAs(\App\Models\User::factory()->create(['ruolo' => 'referente_orario']));

        $tutti = $utente->get('/docenti')->assertOk();
        $tutti->assertSee('Assistenza pause')->assertSee('Sì (1)', false);

        $utente->get('/docenti?ore=non_corrette')->assertOk()->assertSee('Giusti')->assertSee('Eccessi')->assertSee('Mancanti');   // Giusti ha 19/18 per l'assistenza
        $utente->get('/docenti?ore=in_piu')->assertOk()->assertSee('Eccessi')->assertSee('Giusti')->assertDontSee('Mancanti');
        $utente->get('/docenti?ore=mancanti')->assertOk()->assertSee('Mancanti')->assertDontSee('Eccessi')->assertDontSee('Giusti');

        $giusto->assistenzePausa()->get()->each->delete();   // ora Giusti è a 18/18: non compare tra i non corretti
        $utente->get('/docenti?ore=non_corrette')->assertOk()->assertDontSee('Giusti')->assertSee('Eccessi');
    }
}
