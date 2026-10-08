<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Dati delle factory riproducibili: nessun test dipende dal caso (es. un cognome casuale che compare in una pagina).
        fake()->seed(20261008);
        fake()->unique(true);
    }
}
