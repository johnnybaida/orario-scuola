<?php

namespace App\Services\Solver;

use App\Models\CompresenzaSostegno;
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

        \App\Models\AuditLog::registra('Orario', $orario->id, 'generazione', null,
            ['periodo' => $periodo->nome, 'versione' => $versione, 'seed' => $seed, 'punteggio' => $risultato['punteggio'], 'lezioni' => count($risultato['assegnazioni'])],
            "{$periodo->nome} v{$versione}", $creatoDa);

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

        foreach ($risultato['compresenze_sostegno'] as $compresenza) {
            CompresenzaSostegno::query()->create([
                'orario_id' => $orario->id,
                'docente_id' => $compresenza['docente'],
                'classe_id' => $compresenza['classe'],
                'slot_id' => $compresenza['slot'],
                'codice_anonimo' => $compresenza['codice'],
            ]);
        }

        return $orario;
    }
}
