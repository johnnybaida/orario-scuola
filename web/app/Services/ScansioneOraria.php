<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Slot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Orari di inizio e fine di ogni ora e ricreazioni: uguali per tutti i giorni (la scansione è unica di istituto). */
class ScansioneOraria
{
    /** Una riga per ora (ordine => slot): i valori sono quelli del primo giorno, uguali per tutti. */
    public function ore(): Collection
    {
        return Slot::query()->orderBy('ordine')->orderBy('giorno')->get()->groupBy('ordine')->map->first();
    }

    public function descrizione(): array
    {
        return $this->ore()->map(fn (Slot $s) => [
            'inizio' => substr($s->inizio, 0, 5), 'fine' => substr($s->fine, 0, 5), 'ricreazione_minuti' => $s->ricreazione_minuti, 'ricreazione_nome' => $s->ricreazione_nome,
        ])->all();
    }

    /**
     * Imposta le ore (ordine => [inizio, fine, ricreazione]) su tutti i giorni e lo registra nell'audit log.
     * I dati sono già validati (ScansioneOrariaRequest).
     */
    public function applica(array $ore): void
    {
        $prima = $this->descrizione();

        DB::transaction(function () use ($ore) {
            foreach ($ore as $ordine => $ora) {
                Slot::query()->where('ordine', $ordine)->update([
                    'inizio' => $ora['inizio'].':00',
                    'fine' => $ora['fine'].':00',
                    'intervallo_dopo' => ! empty($ora['ricreazione']),
                    'ricreazione_minuti' => ! empty($ora['ricreazione']) ? (int) $ora['ricreazione'] : null,
                    'ricreazione_nome' => ! empty($ora['ricreazione']) && ! empty($ora['nome']) ? $ora['nome'] : null,
                ]);
            }
        });

        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'entita' => 'ScansioneOraria',
            'entita_id' => 0,
            'azione' => 'modifica',
            'dati_prima' => $prima,
            'dati_dopo' => $this->descrizione(),
        ]);
    }
}
