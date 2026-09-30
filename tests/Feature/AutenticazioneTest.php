<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticazioneTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_utente_puo_accedere_con_credenziali_valide(): void
    {
        $user = User::factory()->create([
            'email' => 'referente@scuola.test',
            'password' => 'password',
            'ruolo' => 'referente_orario',
        ]);

        $response = $this->post('/login', [
            'email' => 'referente@scuola.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_credenziali_non_valide_vengono_rifiutate(): void
    {
        User::factory()->create([
            'email' => 'referente@scuola.test',
            'password' => 'password',
        ]);

        $response = $this->post('/login', [
            'email' => 'referente@scuola.test',
            'password' => 'sbagliata',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_un_docente_non_puo_accedere_alle_anagrafiche(): void
    {
        $docente = User::factory()->create(['ruolo' => 'docente']);

        $response = $this->actingAs($docente)->get('/docenti');

        $response->assertForbidden();
    }

    public function test_il_referente_orario_puo_accedere_alle_anagrafiche(): void
    {
        $referente = User::factory()->create(['ruolo' => 'referente_orario']);

        $response = $this->actingAs($referente)->get('/docenti');

        $response->assertOk();
    }
}
