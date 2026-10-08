<?php

namespace Database\Seeders;

use App\Models\Slot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Scansione oraria unica di istituto: settimana corta lun-ven, 6 ore
 * mattutine (50') per tutte le classi; ogni giorno offre anche le ore 7-9
 * pomeridiane, che le classi a tempo prolungato attivano nei giorni di rientro.
 */
class SlotSeeder extends Seeder
{
    public function run(): void
    {
        app(\App\Services\ScansioneOraria::class)->creaStandard();
    }
}
