<?php

use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

/**
 * Ogni giorno della scansione offre le ore 7-9 (pomeriggio), così ogni classe può scegliere
 * liberamente i giorni di rientro. Orari presi da un giorno che già le ha, altrimenti 14:00 con ore da 50'.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Slot::query()->distinct()->pluck('giorno') as $giorno) {
            for ($ordine = 7; $ordine <= 9; $ordine++) {
                if (Slot::query()->where(['giorno' => $giorno, 'ordine' => $ordine])->exists()) {
                    continue;
                }

                $modello = Slot::query()->where('ordine', $ordine)->first();
                $inizio = $modello?->inizio ?? Carbon::createFromTime(14, 0)->addMinutes(50 * ($ordine - 7))->format('H:i:s');
                $fine = $modello?->fine ?? Carbon::createFromTime(14, 0)->addMinutes(50 * ($ordine - 6))->format('H:i:s');

                Slot::query()->create([
                    'giorno' => $giorno, 'ordine' => $ordine, 'inizio' => $inizio, 'fine' => $fine, 'intervallo_dopo' => false,
                ]);
            }
        }
    }

    /** Ripristina la scansione originale: ore pomeridiane solo di martedì e giovedì (perde le attivazioni sugli altri giorni). */
    public function down(): void
    {
        Slot::query()->where('ordine', '>', 6)->whereNotIn('giorno', [2, 4])->delete();
    }
};
