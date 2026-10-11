<?php

namespace App\Constraints;

use App\Constraints\Tipi\C5SpostamentiPiano;
use App\Constraints\Tipi\D1BloccoMinConsecutivo;
use App\Constraints\Tipi\D3MaxOreGiorno;
use App\Constraints\Tipi\D6FasciaOraria;
use App\Constraints\Tipi\D12BloccoMaxConsecutivo;
use App\Constraints\Tipi\D13DisciplinaSeguita;
use App\Constraints\Tipi\S5DistribuzioneSostegno;
use App\Constraints\Tipi\T11OreInFascia;
use App\Constraints\Tipi\T12OreAttornoMensa;
use App\Constraints\Tipi\T2GiornoLibero;
use App\Constraints\Tipi\T3MaxOreBuche;
use App\Constraints\Tipi\T4OreGiorno;

class Catalogo
{
    /** @var array<string, class-string<VincoloTipoInterface>> */
    public const TIPI = [
        'D1_BLOCCO_MIN_CONSECUTIVO' => D1BloccoMinConsecutivo::class,
        'D3_MAX_ORE_GIORNO' => D3MaxOreGiorno::class,
        'D6_FASCIA_ORARIA' => D6FasciaOraria::class,
        'D12_BLOCCO_MAX_CONSECUTIVO' => D12BloccoMaxConsecutivo::class,
        'S5_DISTRIBUZIONE_SOSTEGNO' => S5DistribuzioneSostegno::class,
        'D13_DISCIPLINA_SEGUITA' => D13DisciplinaSeguita::class,
        'T2_GIORNO_LIBERO' => T2GiornoLibero::class,
        'T11_ORE_IN_FASCIA' => T11OreInFascia::class,
        'T12_ORE_ATTORNO_MENSA' => T12OreAttornoMensa::class,
        'T4_ORE_GIORNO' => T4OreGiorno::class,
        'T3_MAX_ORE_BUCHE' => T3MaxOreBuche::class,
        'C5_SPOSTAMENTI_PIANO' => C5SpostamentiPiano::class,
    ];

    /** Raggruppamento dei tipi per le select (optgroup). */
    public const GRUPPI = [
        'Discipline' => ['D1_BLOCCO_MIN_CONSECUTIVO', 'D3_MAX_ORE_GIORNO', 'D6_FASCIA_ORARIA', 'D12_BLOCCO_MAX_CONSECUTIVO', 'D13_DISCIPLINA_SEGUITA'],
        'Docenti' => ['T2_GIORNO_LIBERO', 'T3_MAX_ORE_BUCHE', 'T4_ORE_GIORNO', 'T11_ORE_IN_FASCIA', 'T12_ORE_ATTORNO_MENSA'],
        'Sostegno' => ['S5_DISTRIBUZIONE_SOSTEGNO'],
        'Classi e aule' => ['C5_SPOSTAMENTI_PIANO'],
    ];

    /** @return array<string, array<string, string>> gruppo => [tipo => etichetta]; un tipo nuovo non elencato in GRUPPI finisce in «Altri». */
    public static function gruppi(): array
    {
        $etichette = self::etichette();
        $gruppi = [];
        foreach (self::GRUPPI as $nome => $tipi) {
            $gruppi[$nome] = array_intersect_key(array_replace(array_flip($tipi), $etichette), array_flip($tipi));
        }
        $altri = array_diff_key($etichette, array_flip(array_merge(...array_values(self::GRUPPI))));

        return $altri ? $gruppi + ['Altri' => $altri] : $gruppi;
    }

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
