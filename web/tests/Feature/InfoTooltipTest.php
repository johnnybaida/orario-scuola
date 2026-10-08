<?php

namespace Tests\Feature;

use Tests\TestCase;

class InfoTooltipTest extends TestCase
{
    /** Le intestazioni di tabella hanno `white-space: nowrap` e maiuscoletto: il tooltip non deve ereditarli (testo lungo che esce dal riquadro). */
    public function test_il_tooltip_annulla_gli_stili_ereditati_dalle_intestazioni_delle_tabelle(): void
    {
        $html = (string) $this->blade('<table><thead><tr><th>Titolo <x-info testo="Un testo molto lungo che deve andare a capo" /></th></tr></thead></table>');

        foreach (['whitespace-normal', 'max-w-[22rem]', 'normal-case', 'tracking-normal', 'text-left'] as $classe) {
            $this->assertStringContainsString($classe, $html);
        }
        $this->assertStringContainsString('Un testo molto lungo che deve andare a capo', $html);
    }
}
