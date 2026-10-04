<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScansioneOrariaRequest;
use App\Models\AuditLog;
use App\Models\Slot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Orari di inizio e fine di ogni ora e ricreazioni: uguali per tutti i giorni (la scansione è unica di istituto). */
class ScansioneOrariaController extends Controller
{
    public function index(): View
    {
        return view('scansione.index', ['ore' => $this->ore()]);
    }

    public function update(ScansioneOrariaRequest $request): RedirectResponse
    {
        $prima = $this->descrizione();

        DB::transaction(function () use ($request) {
            foreach ($request->validated('ore') as $ordine => $ora) {
                Slot::query()->where('ordine', $ordine)->update([
                    'inizio' => $ora['inizio'].':00',
                    'fine' => $ora['fine'].':00',
                    'intervallo_dopo' => (bool) ($ora['ricreazione'] ?? false),
                ]);
            }
        });

        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'entita' => 'ScansioneOraria',
            'entita_id' => 0,
            'azione' => 'modifica',
            'dati_prima' => $prima,
            'dati_dopo' => $this->descrizione(),
        ]);

        return redirect()->route('scansione.index')->with('successo', 'Scansione oraria aggiornata.');
    }

    /** Una riga per ora (ordine => slot): i valori sono quelli del primo giorno, uguali per tutti. */
    private function ore(): Collection
    {
        return Slot::query()->orderBy('ordine')->orderBy('giorno')->get()->groupBy('ordine')->map->first();
    }

    private function descrizione(): array
    {
        return $this->ore()->map(fn (Slot $s) => [
            'inizio' => substr($s->inizio, 0, 5), 'fine' => substr($s->fine, 0, 5), 'ricreazione_dopo' => $s->intervallo_dopo,
        ])->all();
    }
}
