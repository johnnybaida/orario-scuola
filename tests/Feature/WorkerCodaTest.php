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
        @unlink(app(QueueWorker::class)->pidFile());
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
    }

    public function test_solo_chi_gestisce_lanagrafica_puo_fermare_il_worker(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'segreteria']))->post('/worker/ferma')->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->post('/worker/ferma')->assertRedirect();
        $this->assertNotNull(Cache::get('illuminate:queue:restart'));
    }
}
