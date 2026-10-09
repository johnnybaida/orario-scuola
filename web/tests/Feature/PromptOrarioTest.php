<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Slot;
use App\Models\User;
use App\Models\Vincolo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptOrarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_il_prompt_elenca_aule_docenti_cattedre_e_vincoli_ed_e_scaricabile(): void
    {
        $slot = Slot::factory()->create(['giorno' => 1, 'ordine' => 1]);
        $aula = Aula::factory()->create(['nome' => 'Palestra A', 'tipo' => 'palestra']);
        $classe = Classe::factory()->create(['anno_corso' => 1, 'sezione' => 'A']);
        $classe->slotAttivi()->sync([$slot->id]);
        $docente = Docente::factory()->create(['cognome' => 'Rossi', 'nome' => 'Anna']);
        $docente->indisponibilita()->attach($slot->id);
        $disciplina = Disciplina::factory()->create(['codice' => 'MOT', 'nome' => 'Motoria', 'tipo_aula_richiesto' => 'palestra']);
        Cattedra::factory()->create(['classe_id' => $classe->id, 'docente_id' => $docente->id, 'disciplina_id' => $disciplina->id, 'ore' => 2]);
        Vincolo::factory()->create(['tipo' => 'D6_FASCIA_ORARIA', 'ambito_livello' => 'globale', 'ambito_ids' => null, 'severita' => 'preferenziale', 'peso' => 40,
            'parametri' => ['disciplina_id' => $disciplina->id, 'tipo' => 'vietata', 'slot_ids' => [$slot->id]]]);

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']));
        $this->get('/prompt-ai')->assertOk()->assertSee('Copia il testo')->assertSee('Palestra A')->assertSee('Rossi Anna')
            ->assertSee('Motoria in 1ª A (2h')->assertSee('Indisponibile: LUN 1ª')->assertSee('preferenziale (peso 40/100)')->assertSee('disciplina: Motoria');

        $file = $this->get('/prompt-ai?scarica=1')->assertOk();
        $this->assertStringContainsString('attachment; filename="prompt-orario.txt"', $file->headers->get('Content-Disposition'));
        $this->assertStringContainsString('## Docenti e cattedre', $file->getContent());   // il file è il testo puro, non la pagina
        $this->assertStringNotContainsString('<html', $file->getContent());
    }

    public function test_il_docente_senza_permessi_di_consultazione_non_vede_il_prompt(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']));

        $this->get('/prompt-ai')->assertForbidden();
    }
}
