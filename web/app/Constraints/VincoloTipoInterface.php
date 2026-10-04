<?php

namespace App\Constraints;

interface VincoloTipoInterface
{
    /** Nome breve per le select della UI. */
    public function etichetta(): string;

    /** Livelli di ambito consentiti per questo tipo (es. ['classe', 'globale']). */
    public function ambitiConsentiti(): array;

    /** Regole di validazione Laravel per la colonna JSON `parametri`. */
    public function regoleParametri(): array;

    /** Descrizione in linguaggio naturale, per l'anteprima nella UI vincoli. */
    public function descrizione(array $parametri): string;
}
