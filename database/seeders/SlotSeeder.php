<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Seeder;

/**
 * Scansione oraria unica di istituto: settimana corta lun-ven, 6 ore
 * mattutine (50') per tutte le classi; martedì e giovedì aggiungono 3 ore
 * pomeridiane per le classi a tempo prolungato.
 */
class SlotSeeder extends Seeder
{
    public function run(): void
    {
        $durataMinuti = 50;
        $giorniRientro = [2, 4]; // martedì, giovedì

        for ($giorno = 1; $giorno <= 5; $giorno++) {
            $inizio = \Carbon\Carbon::createFromTime(8, 0);

            for ($ordine = 1; $ordine <= 6; $ordine++) {
                $fine = $inizio->copy()->addMinutes($durataMinuti);
                $intervalloDopo = $ordine === 3;

                Slot::query()->create([
                    'giorno' => $giorno,
                    'ordine' => $ordine,
                    'inizio' => $inizio->format('H:i:s'),
                    'fine' => $fine->format('H:i:s'),
                    'intervallo_dopo' => $intervalloDopo,
                ]);

                $inizio = $fine->copy()->addMinutes($intervalloDopo ? 10 : 0);
            }

            if (in_array($giorno, $giorniRientro, true)) {
                $inizio = \Carbon\Carbon::createFromTime(14, 0);

                for ($ordine = 7; $ordine <= 9; $ordine++) {
                    $fine = $inizio->copy()->addMinutes($durataMinuti);

                    Slot::query()->create([
                        'giorno' => $giorno,
                        'ordine' => $ordine,
                        'inizio' => $inizio->format('H:i:s'),
                        'fine' => $fine->format('H:i:s'),
                        'intervallo_dopo' => false,
                    ]);

                    $inizio = $fine;
                }
            }
        }
    }
}
