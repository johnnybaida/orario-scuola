<?php

namespace Tests\Feature;

use App\Models\AssistenzaPausa;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Impostazioni;
use App\Models\Laboratorio;
use App\Models\Slot;
use App\Models\Sospensione;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Ogni dato dell'anagrafica (esclusi vincoli e sostegno) deve poter uscire e rientrare con i CSV. */
class CsvCompletoTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $lista): string
    {
        return $this->get("/csv/{$lista}")->assertOk()->streamedContent();
    }

    private function carica(string $lista, string $csv)
    {
        return $this->post("/csv/{$lista}/importa", ['file' => UploadedFile::fake()->createWithContent("{$lista}.csv", $csv)])->assertOk();
    }

    public function test_le_liste_esistenti_portano_anche_i_dati_nuovi_e_rientrano(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $sede = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        Slot::factory()->create(['giorno' => 1, 'ordine' => 2]);
        $classe = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A', 'conteggio_sostegno' => 'per_classe']);
        $classe->slotAttivi()->sync([Slot::query()->where('ordine', 2)->value('id')]);   // solo la 2ª ora: non è il default del mattino
        $docente = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $docente->classiConcorso()->create(['classe_concorso' => 'A022']);
        $clil = Docente::factory()->create(['cognome' => 'Smith', 'nome' => 'Emma']);
        $disciplina = Disciplina::factory()->create(['codice' => 'GEO', 'tipi_aula_extra' => ['dada_ita', 'dada_ing']]);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $docente->id, 'disciplina_id' => $disciplina->id, 'ore' => 2, 'docente_clil_id' => $clil->id, 'ore_clil' => 1]);

        $csv = ['classi' => $this->csv('classi'), 'docenti' => $this->csv('docenti'), 'discipline' => $this->csv('discipline'), 'cattedre' => $this->csv('cattedre')];
        $this->assertStringContainsString('per_classe;LUN.2', $csv['classi']);
        $this->assertStringContainsString('A022', $csv['docenti']);
        $this->assertStringContainsString('dada_ita|dada_ing', $csv['discipline']);
        $this->assertStringContainsString('Smith;Emma;1', $csv['cattedre']);

        // svuota e reimporta nell'ordine delle dipendenze
        Cattedra::query()->get()->each->delete();
        Classe::query()->get()->each->delete();
        Docente::query()->get()->each->delete();
        Disciplina::query()->get()->each->delete();
        foreach (['discipline', 'docenti', 'classi'] as $lista) {
            $this->carica($lista, $csv[$lista]);
        }
        $this->carica('cattedre', $csv['cattedre'])->assertDontSee('non trovat');

        $this->assertSame(['A022'], Docente::query()->where('cognome', 'Rossi')->first()->classiConcorso->pluck('classe_concorso')->all());
        $this->assertEqualsCanonicalizing(['dada_ita', 'dada_ing'], Disciplina::query()->where('codice', 'GEO')->first()->tipi_aula_extra);
        $nuova = Classe::query()->first();
        $this->assertSame('per_classe', $nuova->conteggio_sostegno);
        $this->assertSame([Slot::query()->where('ordine', 2)->value('id')], $nuova->slotAttivi()->pluck('slot.id')->all());
        $cattedra = Cattedra::query()->first();
        $this->assertSame([1, 'Smith'], [$cattedra->ore_clil, $cattedra->docenteClil->cognome]);
    }

    public function test_indisponibilita_sospensioni_assistenze_laboratori_e_impostazioni_escono_e_rientrano(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $s1 = Slot::factory()->create(['giorno' => 1, 'ordine' => 1, 'inizio' => '08:00:00', 'fine' => '08:50:00', 'ricreazione_minuti' => 40]);
        $s7 = Slot::factory()->create(['giorno' => 2, 'ordine' => 7, 'inizio' => '14:00:00', 'fine' => '14:50:00']);
        $rossi = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $bianchi = Docente::factory()->create(['cognome' => 'De Luca', 'nome' => 'Luca']);
        $classe = Classe::factory()->create(['anno_corso' => 2, 'sezione' => 'B']);
        $aula = Aula::factory()->create(['nome' => 'Lab latino', 'tipo' => 'laboratorio']);

        $rossi->indisponibilita()->attach($s1->id);
        $sosp = $rossi->sospensioni()->create(['dal' => '2026-10-01', 'al' => null, 'motivo' => 'malattia', 'esclude_da_orario' => true, 'note' => 'prognosi lunga']);
        $sosp->supplenti()->sync([$bianchi->id]);
        $rossi->assistenzePausa()->create(['giorno' => 1, 'ordine' => 1]);
        $lab = Laboratorio::factory()->create(['nome' => 'Latino', 'aula_id' => $aula->id, 'n_partecipanti' => 12, 'attivo' => true]);
        $lab->docenti()->sync([$bianchi->id]);
        $lab->classi()->sync([$classe->id]);
        $lab->slot()->sync([$s7->id]);
        Impostazioni::correnti()->update(['conteggio_sostegno' => 'per_classe']);

        $liste = ['indisponibilita', 'sospensioni', 'assistenze-pausa', 'laboratori', 'impostazioni'];
        $csv = collect($liste)->mapWithKeys(fn ($l) => [$l => $this->csv($l)])->all();
        $this->assertStringContainsString('Rossi;Anna;LUN;1', $csv['indisponibilita']);
        $this->assertStringContainsString('Rossi;Anna;2026-10-01;;malattia;1;"prognosi lunga";"De Luca Luca"', $csv['sospensioni']);
        $this->assertStringContainsString('Rossi;Anna;LUN;1', $csv['assistenze-pausa']);
        $this->assertStringContainsString('Latino;"Lab latino";12;1;;"De Luca Luca";2B;MAR.7', $csv['laboratori']);

        $rossi->indisponibilita()->detach();
        $rossi->sospensioni()->get()->each->delete();
        AssistenzaPausa::query()->get()->each->delete();
        Laboratorio::query()->get()->each->delete();
        Impostazioni::correnti()->update(['conteggio_sostegno' => 'per_alunno']);

        foreach ($liste as $lista) {
            $this->carica($lista, $csv[$lista])->assertDontSee('con errori');
        }

        $this->assertTrue($rossi->indisponibilita()->whereKey($s1->id)->exists());
        $sosp = Sospensione::query()->first();
        $this->assertSame(['malattia', true, $bianchi->id], [$sosp->motivo, $sosp->esclude_da_orario, $sosp->supplenti->first()->id]);
        $this->assertSame(1, AssistenzaPausa::query()->where('ordine', 1)->count());
        $laboratorio = Laboratorio::query()->first();
        $this->assertSame([[$bianchi->id], [$classe->id], [$s7->id]], [$laboratorio->docenti->pluck('id')->all(), $laboratorio->classi->pluck('id')->all(), $laboratorio->slot->pluck('id')->all()]);
        $this->assertSame('per_classe', Impostazioni::correnti()->conteggio_sostegno);

        // reimportare lo stesso file non duplica nulla
        $this->carica('sospensioni', $csv['sospensioni'])->assertSee('1 già presenti');
        $this->assertSame(1, Sospensione::query()->count());
    }

    public function test_errori_leggibili_nelle_liste_nuove(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);

        $this->carica('indisponibilita', "docente_cognome;docente_nome;giorno;ora\nRossi;Anna;LUN;9\nNessuno;Mai;LUN;1\n")
            ->assertSee('l\'ora LUN.9 non esiste')->assertSee('docente Nessuno Mai non trovato');
        $this->carica('assistenze-pausa', "docente_cognome;docente_nome;giorno;dopo_ora\nRossi;Anna;LUN;5\n")->assertSee('non esiste una pausa dopo l\'ora');
    }
}
