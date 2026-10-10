<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuProfiloTest extends TestCase
{
    use RefreshDatabase;

    private function pagina(string $ruolo, string $nome = 'Maria Rossi'): array
    {
        $html = $this->actingAs(User::factory()->create(['ruolo' => $ruolo, 'name' => $nome]))->get('/dashboard')->assertOk()->getContent();
        preg_match('#<aside class="bg-primary.*?</aside>#s', $html, $sidebar);
        preg_match('#<details data-menu-profilo.*?</details>#s', $html, $profilo);

        return [$sidebar[0] ?? '', $profilo[0] ?? ''];
    }

    public function test_l_amministratore_ha_nome_ruolo_voci_di_amministrazione_ed_esci_nel_menu_del_profilo(): void
    {
        [$sidebar, $profilo] = $this->pagina('amministratore');

        $this->assertStringContainsString('>MR</summary>', $profilo);                       // le iniziali sull'icona
        foreach (['Maria Rossi', 'Amministratore', 'Impostazioni', 'Utenze', 'Dati', 'Registro attività', 'Esci'] as $voce) {
            $this->assertStringContainsString($voce, $profilo);
        }
        foreach (['Impostazioni', 'Utenze', 'Registro attività', 'Esci', 'Maria Rossi'] as $voce) {
            $this->assertStringNotContainsString($voce, $sidebar);                          // non sono più nella sidebar
        }
        $this->assertStringContainsString('Versione', $sidebar);
        $this->assertStringContainsString('Vincoli', $sidebar);                              // le voci operative restano
    }

    public function test_le_voci_del_menu_del_profilo_seguono_i_permessi(): void
    {
        [, $ds] = $this->pagina('ds', 'Anna Verdi');
        $this->assertStringContainsString('Registro attività', $ds);
        $this->assertStringContainsString('Impostazioni', $ds);                              // chi consulta le vede (in sola lettura)
        $this->assertStringNotContainsString('Utenze', $ds);
        $this->assertStringNotContainsString('>Dati<', $ds);

        [, $referente] = $this->pagina('referente_orario');
        $this->assertStringNotContainsString('Registro attività', $referente);
        $this->assertStringNotContainsString('Utenze', $referente);
        $this->assertStringContainsString('Esci', $referente);

        [, $docente] = $this->pagina('docente', 'Luca');
        $this->assertStringContainsString('>L</summary>', $docente);                         // un solo nome: una sola iniziale
        $this->assertStringNotContainsString('Impostazioni', $docente);                      // il ruolo docente non ha accesso
        $this->assertStringContainsString('Esci', $docente);
    }

    public function test_a_sinistra_di_aiuto_c_e_il_pulsante_schermo_intero(): void
    {
        $html = $this->actingAs(\App\Models\User::factory()->create(['ruolo' => 'referente_orario']))->get('/dashboard')->assertOk()->getContent();

        $schermo = strpos($html, 'data-schermo-intero');
        $aiuto = strpos($html, 'data-apri-guida');
        $this->assertNotFalse($schermo);
        $this->assertLessThan($aiuto, $schermo);   // prima nel documento = a sinistra
        $this->assertStringContainsString('aria-label="Schermo intero"', $html);
    }
}
