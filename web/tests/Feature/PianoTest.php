<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Sede;
use App\Models\User;
use App\Models\Vincolo;
use App\Services\Solver\ProblemBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PianoTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_il_piano_di_aule_e_classi_si_salva_e_arriva_al_solver(): void
    {
        $sede = Sede::factory()->create();
        $this->actingAs($this->referente())
            ->post('/aule', ['sede_id' => $sede->id, 'nome' => 'Lab', 'tipo' => 'laboratorio_informatica', 'capienza' => 1, 'piano' => 3])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('aule', ['nome' => 'Lab', 'piano' => 3]);

        $this->post('/aule', ['sede_id' => $sede->id, 'nome' => 'Fuori', 'tipo' => 'palestra', 'capienza' => 1, 'piano' => 99])->assertSessionHasErrors('piano');

        $classe = Classe::factory()->create(['piano' => -1]);
        $this->put("/classi/{$classe->id}", [
            'anno_corso' => $classe->anno_corso, 'sezione' => $classe->sezione, 'sede_id' => $classe->sede_id, 'quadro_orario_id' => $classe->quadro_orario_id,
            'tempo_scuola' => $classe->tempo_scuola, 'n_alunni' => $classe->n_alunni, 'piano' => 2,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $classe->fresh()->piano);

        $problema = app(ProblemBuilder::class)->costruisci(1, 10);
        $this->assertSame(3, collect($problema['aule'])->firstWhere('id', Aula::query()->where('nome', 'Lab')->value('id'))['piano']);
        $this->assertSame(2, collect($problema['classi'])->firstWhere('id', $classe->id)['piano']);
    }

    public function test_il_vincolo_c5_si_crea_per_classi_e_globale_con_soglia_opzionale(): void
    {
        $classe = Classe::factory()->create();
        $base = ['tipo' => 'C5_SPOSTAMENTI_PIANO', 'severita' => 'preferenziale', 'peso' => 30, 'parametri' => ['soglia' => 1]];

        $this->actingAs($this->referente())->post('/vincoli', $base + ['ambito_livello' => 'globale'])->assertSessionHasNoErrors();
        $this->post('/vincoli', $base + ['ambito_livello' => 'classe', 'ambito_ids' => [$classe->id], 'parametri' => []])->assertSessionHasNoErrors();
        $this->assertSame(2, Vincolo::query()->where('tipo', 'C5_SPOSTAMENTI_PIANO')->count());

        $this->post('/vincoli', $base + ['ambito_livello' => 'docente'])->assertSessionHasErrors('ambito_livello');
        $this->post('/vincoli', ['parametri' => ['soglia' => 50]] + $base + ['ambito_livello' => 'globale'])->assertSessionHasErrors('parametri.soglia');
    }

    public function test_il_csv_di_aule_e_classi_include_il_piano_e_accetta_file_senza(): void
    {
        $admin = User::factory()->create(['ruolo' => 'amministratore']);
        $sede = Sede::factory()->create(['nome' => 'Centrale']);
        Aula::factory()->create(['sede_id' => $sede->id, 'nome' => 'Lab1', 'piano' => 2]);
        $this->actingAs($admin);

        $this->assertStringContainsString('Lab1', $this->get('/csv/aule')->streamedContent());
        $this->assertStringContainsString('capienza;piano', $this->get('/csv/aule')->streamedContent());

        // un file vecchio, senza la colonna piano, si importa lo stesso
        $csv = "sede;nome;tipo;capienza\nCentrale;Lab2;palestra;2\n";
        $this->post('/csv/aule/importa', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('aule.csv', $csv)])->assertOk()->assertSee('Importate 1 righe');
        $this->assertDatabaseHas('aule', ['nome' => 'Lab2', 'piano' => null]);
    }
}
