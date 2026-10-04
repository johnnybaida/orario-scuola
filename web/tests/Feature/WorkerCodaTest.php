<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QueueWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WorkerCodaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(storage_path('app/queue-worker.pid'));
        @unlink(storage_path('app/queue-worker.stop'));
        parent::tearDown();
    }

    public function test_stato_attivo_se_il_pid_esiste_e_fermo_altrimenti(): void
    {
        $worker = app(QueueWorker::class);

        file_put_contents($worker->pidFile(), '999999');
        $this->assertFalse($worker->attivo());

        file_put_contents($worker->pidFile(), (string) getmypid());
        $this->assertTrue($worker->attivo());
        $this->assertFalse($worker->avvia()); // già attivo: non ne lancia un secondo

        $worker->ferma();
        $this->assertTrue($worker->inArresto()); // in arresto: Avvia è di nuovo consentito
    }

    public function test_solo_chi_gestisce_lanagrafica_puo_fermare_il_worker(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))->post('/worker/ferma')->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->post('/worker/ferma')->assertRedirect();
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }

    public function test_avvia_generazione_avvia_il_worker_se_la_coda_e_ferma(): void
    {
        config(['queue.default' => 'database']);
        $this->mock(QueueWorker::class)->shouldReceive('avvia')->once()->andReturn(true);
        $this->seed(\Database\Seeders\ImpostazioniSeeder::class);

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))
            ->post('/generazioni', ['time_limit_s' => 10])
            ->assertRedirect()
            ->assertSessionHas('successo');
    }

    public function test_se_il_worker_non_parte_la_generazione_resta_in_coda_con_il_motivo(): void
    {
        config(['queue.default' => 'database']);
        $this->mock(QueueWorker::class)->shouldReceive('avvia')->andThrow(new \RuntimeException('php non trovato'));
        $this->seed(\Database\Seeders\ImpostazioniSeeder::class);

        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))
            ->post('/generazioni', ['time_limit_s' => 10])
            ->assertRedirect()
            ->assertSessionHasErrors('worker');
        $this->assertDatabaseHas('generazioni', ['stato' => 'in_coda']);
    }
}
