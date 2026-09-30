<?php

namespace App\Services\Solver;

use App\Models\Lezione;
use App\Models\Orario;
use App\Models\Periodo;

/**
 * Importa la soluzione JSON del solver (stato "ottimo"/"fattibile") creando
 * l'Orario e le Lezioni corrispondenti.
 */
class ResultImporter
{
    public function importa(Periodo $periodo, int $seed, array $risultato, array $mappaLezioni, ?int $creatoDa = null): Orario
    {
        $versione = (Orario::query()->where('periodo_id', $periodo->id)->max('versione') ?? 0) + 1;

        $orario = Orario::query()->create([
            'periodo_id' => $periodo->id,
            'versione' => $versione,
            'stato' => 'bozza',
            'seed' => $seed,
            'punteggio' => $risultato['punteggio'],
            'creato_da' => $creatoDa,
        ]);

        foreach ($risultato['assegnazioni'] as $assegnazione) {
            Lezione::query()->create([
                'orario_id' => $orario->id,
                'cattedra_id' => $mappaLezioni[$assegnazione['lezione']],
                'slot_id' => $assegnazione['slot'],
                'durata_slot' => 1,
                'aula_id' => $assegnazione['aula'],
                'bloccata' => false,
            ]);
        }

        return $orario;
    }
}
