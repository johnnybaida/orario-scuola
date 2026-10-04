<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtenzeTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_lamministratore_gestisce_le_utenze(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get('/utenze')->assertForbidden();
    }

    public function test_crea_modifica_ed_elimina_utenza_ma_non_se_stessi(): void
    {
        $admin = User::factory()->create(['ruolo' => 'amministratore']);
        $this->actingAs($admin);

        $this->post('/utenze', ['name' => 'Ada', 'email' => 'ada@scuola.test', 'ruolo' => 'segreteria', 'password' => 'password1'])
            ->assertRedirect(route('utenze.index'));
        $ada = User::query()->where('email', 'ada@scuola.test')->firstOrFail();

        // Password vuota in modifica = invariata.
        $hash = $ada->password;
        $this->put("/utenze/{$ada->id}", ['name' => 'Ada L.', 'email' => 'ada@scuola.test', 'ruolo' => 'ds', 'password' => ''])
            ->assertRedirect(route('utenze.index'));
        $this->assertSame('ds', $ada->fresh()->ruolo);
        $this->assertSame($hash, $ada->fresh()->password);

        $this->put("/utenze/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'ruolo' => 'ds'])
            ->assertSessionHasErrors('ruolo');
        $this->delete("/utenze/{$admin->id}")->assertStatus(422);

        $this->delete("/utenze/{$ada->id}")->assertRedirect(route('utenze.index'));
        $this->assertModelMissing($ada);
    }
}
