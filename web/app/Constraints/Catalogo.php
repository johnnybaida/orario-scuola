<?php

namespace App\Constraints;

use App\Constraints\Tipi\D1BloccoMinConsecutivo;
use App\Constraints\Tipi\D3MaxOreGiorno;
use App\Constraints\Tipi\D6FasciaOraria;
use App\Constraints\Tipi\T2GiornoLibero;
use App\Constraints\Tipi\T3MaxOreBuche;

class Catalogo
{
    /** @var array<string, class-string<VincoloTipoInterface>> */
    public const TIPI = [
        'D1_BLOCCO_MIN_CONSECUTIVO' => D1BloccoMinConsecutivo::class,
        'D3_MAX_ORE_GIORNO' => D3MaxOreGiorno::class,
        'D6_FASCIA_ORARIA' => D6FasciaOraria::class,
        'T2_GIORNO_LIBERO' => T2GiornoLibero::class,
        'T3_MAX_ORE_BUCHE' => T3MaxOreBuche::class,
    ];

    public static function istanza(string $tipo): VincoloTipoInterface
    {
        $classe = self::TIPI[$tipo] ?? null;

        if (! $classe) {
            throw new \InvalidArgumentException("Tipo di vincolo sconosciuto: {$tipo}");
        }

        return new $classe;
    }

    /** @return array<string, string> tipo => etichetta, per la select della UI. */
    public static function etichette(): array
    {
        $etichette = [];
        foreach (array_keys(self::TIPI) as $tipo) {
            $etichette[$tipo] = self::istanza($tipo)->etichetta();
        }

        return $etichette;
    }
}
