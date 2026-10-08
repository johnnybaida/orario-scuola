<?php

namespace Tests\Feature;

use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Generazione;
use App\Models\Orario;
use App\Models\QuadroOrario;
use App\Models\Slot;
use App\Models\User;
use App\Services\Solver\DiagnosticaSolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorreggiGenerazioneTest extends TestCase
{
    use RefreshDatabase;

    private function referente(): User
    {
        return User::factory()->create(['ruolo' => 'referente_orario']);
    }

    public function test_una_generazione_bloccata_dai_controlli_mostra_il_pulsante_correggi_con_il_link_giusto(): void
    {
        foreach ([1, 2] as $ordine) {
            Slot::factory()->create(['giorno' => 1, 'ordine' => $ordine]);
        }
        $quadro = QuadroOrario::factory()->create(['ore_totali' => 2]);
        $classe = Classe::factory()->create(['quadro_orario_id' => $quadro->id]);
        $classe->slotAttivi()->sync(Slot::query()->pluck('id'));   // quadro da 2 ore ma nessuna cattedra: la pre-validazione lo segnala

        $this->actingAs($this->referente())->post('/generazioni', ['time_limit_s' => 30, 'seed' => 1]);
        $generazione = Generazione::query()->latest('id')->firstOrFail();

        $this->assertSame('infattibile', $generazione->stato);
        $this->assertSame(route('classi.edit', $classe), $generazione->righeDiagnostica()[0]['url']);
        $this->get(route('generazioni.show', $generazione))->assertOk()->assertSee('Correggi')
            ->assertSee(route('classi.edit', $classe), false)->assertSee('genera di nuovo');
    }

    public function test_le_generazioni_vecchie_con_righe_di_solo_testo_si_vedono_senza_pulsante(): void
    {
        $orario = Orario::factory()->create();
        $g = Generazione::query()->create(['periodo_id' => $orario->periodo_id, 'seed' => 1, 'time_limit_s' => 10, 'stato' => 'infattibile', 'diagnostica' => ['Un vecchio errore']]);

        $this->actingAs($this->referente())->get(route('generazioni.show', $g))->assertOk()->assertSee('Un vecchio errore')->assertDontSee('>Correggi<', false);
        $this->get(route('generazioni.diagnostica', $g))->assertOk()->assertSee('Un vecchio errore');   // il rapporto per l'assistenza legge entrambi i formati
    }

    public function test_le_righe_del_solver_ricevono_il_link_alla_pagina_che_le_risolve(): void
    {
        $cattedra = Cattedra::factory()->create();
        $righe = app(DiagnosticaSolver::class)->conLink([
            'Lezione 3 (disciplina ITA): nessuno slot disponibile (indisponibilità docente o slot attivi della classe insufficienti).',
            "Lezione 4: richiede aula di tipo 'palestra' ma nessuna è censita.",
            'Sostegno classe '.$cattedra->classe_id.': il docente 9 ha solo 2 slot disponibili ma gli sono state assegnate 6h.',
            'Nessuna soluzione soddisfa i vincoli rigidi con i dati forniti.',
            'Tempo limite raggiunto senza trovare una soluzione.',
            'Un messaggio sconosciuto.',
        ], [3 => $cattedra->id]);

        $this->assertSame([
            route('docenti.edit', $cattedra->docente_id), route('aule.index'), route('classi.edit', $cattedra->classe_id),
            route('vincoli.index'), route('generazioni.create'), null,
        ], array_column($righe, 'url'));
        $this->assertSame('Un messaggio sconosciuto.', $righe[5]['testo']);
    }
}
