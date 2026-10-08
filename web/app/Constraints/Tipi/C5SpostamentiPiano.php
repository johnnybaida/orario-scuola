<?php

namespace App\Constraints\Tipi;

use App\Constraints\VincoloTipoInterface;

class C5SpostamentiPiano implements VincoloTipoInterface
{
    public function etichetta(): string
    {
        return 'Spostamenti tra piani (C5)';
    }

    public function ambitiConsentiti(): array
    {
        return ['classe', 'globale'];
    }

    public function regoleParametri(): array
    {
        return [
            'soglia' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }

    public function descrizione(array $parametri): string
    {
        $soglia = (int) ($parametri['soglia'] ?? 0);

        return $soglia > 0
            ? "Spostamenti tra piani: tra due ore consecutive la classe non cambia più di {$soglia} piani senza penalità."
            : 'Spostamenti tra piani: la classe cambia piano il meno possibile tra due ore consecutive.';
    }
}
