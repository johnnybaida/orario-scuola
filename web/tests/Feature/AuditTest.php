<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_creazione_modifica_ed_eliminazione_sono_registrate_con_utente_e_valori(): void
    {
        $admin = User::factory()->create(['ruolo' => 'amministratore']);
        $this->actingAs($admin);

        $sede = Sede::query()->create(['nome' => 'Centrale', 'indirizzo' => 'Via Roma 1']);
        $sede->update(['nome' => 'Principale']);
        $id = $sede->id;
        $sede->delete();

        $voci = AuditLog::query()->where('entita', 'Sede')->where('entita_id', $id)->orderBy('id')->get();
        $this->assertSame(['creazione', 'modifica', 'eliminazione'], $voci->pluck('azione')->all());
        $this->assertSame($admin->id, $voci[0]->user_id);
        $this->assertSame(['nome' => 'Centrale'], $voci[1]->dati_prima);
        $this->assertSame(['nome' => 'Principale'], $voci[1]->dati_dopo);
        $this->assertSame('Principale', $voci[2]->etichetta); // il nome resta dopo l'eliminazione
        $this->assertSame('Principale', $voci[2]->dati_prima['nome']);
    }

    public function test_una_modifica_senza_cambiamenti_non_e_registrata_e_le_password_non_compaiono(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'amministratore']));
        $docente = Docente::factory()->create();
        $docente->update(['nome' => $docente->nome]);
        $this->assertSame(1, AuditLog::query()->where('entita', 'Docente')->count());

        $utente = User::factory()->create();
        $utente->update(['password' => 'nuova-password-segreta']);
        $voci = AuditLog::query()->where('entita', 'User')->where('entita_id', $utente->id)->get();
        $this->assertStringNotContainsString('nuova-password-segreta', $voci->toJson());
        $this->assertSame('***', $voci->last()->dati_dopo['password']);
    }

    public function test_le_operazioni_dal_controller_sono_registrate_e_il_seeder_no(): void
    {
        $this->seed();
        $this->assertSame(0, AuditLog::query()->count());

        $referente = User::factory()->create(['ruolo' => 'referente_orario']);
        $sede = Sede::factory()->create();   // l'ultima sede non si elimina
        $this->actingAs($referente)->delete(route('sedi.destroy', $sede));
        $this->assertDatabaseHas('audit_log', ['entita' => 'Sede', 'entita_id' => $sede->id, 'azione' => 'eliminazione', 'user_id' => $referente->id]);
    }

    public function test_pagina_registro_solo_per_chi_approva_con_filtri(): void
    {
        $admin = User::factory()->create(['ruolo' => 'amministratore']);
        $this->actingAs($admin);
        Disciplina::query()->create(['codice' => 'NRD', 'nome' => 'Disciplina Nord']);
        Docente::factory()->create(['cognome' => 'Rossi']);

        $this->get(route('audit.index'))->assertOk()->assertSee('Disciplina Nord')->assertSee('Rossi');
        $this->get(route('audit.index', ['entita' => 'Disciplina']))->assertOk()->assertSee('Disciplina Nord')->assertDontSee('Rossi');
        $this->get(route('audit.index', ['cerca' => 'Rossi']))->assertOk()->assertDontSee('Disciplina Nord');

        $this->actingAs(User::factory()->create(['ruolo' => 'ds']))->get(route('audit.index'))->assertOk();
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']))->get(route('audit.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['ruolo' => 'docente']))->get(route('audit.index'))->assertForbidden();
    }
}
