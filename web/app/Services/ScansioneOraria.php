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
            'pausa_prima_minuti' => $s->pausa_prima_minuti, 'pausa_prima_nome' => $s->pausa_prima_nome,
        ])->all();
    }

    /** Scansione standard della sede corrente: lun-ven, 6 ore al mattino da 50' (ricreazione di 10' dopo la 3ª) e 3 ore al pomeriggio. */
    public function creaStandard(): void
    {
        $durata = 50;
        DB::transaction(function () use ($durata) {
            for ($giorno = 1; $giorno <= 5; $giorno++) {
                $inizio = \Carbon\Carbon::createFromTime(8, 0);
                for ($ordine = 1; $ordine <= 9; $ordine++) {
                    if ($ordine === 7) {
                        $inizio = \Carbon\Carbon::createFromTime(14, 0);
                    }
                    $fine = $inizio->copy()->addMinutes($durata);
                    $pausa = $ordine === 3;
                    Slot::query()->create([
                        'giorno' => $giorno, 'ordine' => $ordine, 'inizio' => $inizio->format('H:i:s'), 'fine' => $fine->format('H:i:s'),
                        'intervallo_dopo' => $pausa, 'ricreazione_minuti' => $pausa ? 10 : null,
                    ]);
                    $inizio = $fine->copy()->addMinutes($pausa ? 10 : 0);
                }
            }
        });
        AuditLog::registra('ScansioneOraria', 0, 'creazione', null, ['standard' => true]);
    }

    /**
     * Imposta le ore (ordine => [inizio, fine, ricreazione, nome]) e la pausa prima della prima ora ([minuti, nome]) su tutti i giorni e lo registra nell'audit log.
     * I dati sono già validati (ScansioneOrariaRequest).
     */
    public function applica(array $ore, array $pausaPrima = []): void
    {
        $prima = $this->descrizione();

        DB::transaction(function () use ($ore, $pausaPrima) {
            foreach ($ore as $ordine => $ora) {
                Slot::query()->where('ordine', $ordine)->update([
                    'inizio' => $ora['inizio'].':00',
                    'fine' => $ora['fine'].':00',
                    'intervallo_dopo' => ! empty($ora['ricreazione']),
                    'ricreazione_minuti' => ! empty($ora['ricreazione']) ? (int) $ora['ricreazione'] : null,
                    'ricreazione_nome' => ! empty($ora['ricreazione']) && ! empty($ora['nome']) ? $ora['nome'] : null,
                ]);
            }
            Slot::query()->update(['pausa_prima_minuti' => null, 'pausa_prima_nome' => null]);
            if (! empty($pausaPrima['minuti']) && $ore) {
                Slot::query()->where('ordine', min(array_keys($ore)))->update([
                    'pausa_prima_minuti' => (int) $pausaPrima['minuti'], 'pausa_prima_nome' => ! empty($pausaPrima['nome']) ? $pausaPrima['nome'] : null,
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
