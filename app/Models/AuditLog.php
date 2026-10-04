<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'entita', 'entita_id', 'etichetta', 'azione', 'dati_prima', 'dati_dopo'])]
class AuditLog extends Model
{
    protected $table = 'audit_log';

    const CREATED_AT = 'creato_il';

    const UPDATED_AT = null;

    /** false = non registrare le operazioni automatiche dei modelli (es. caricamento della scuola di esempio). */
    public static bool $attivo = true;

    protected function casts(): array
    {
        return [
            'dati_prima' => 'array',
            'dati_dopo' => 'array',
            'creato_il' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Esegue $operazione senza registrare le modifiche dei modelli (seeder). */
    public static function senza(callable $operazione): mixed
    {
        $precedente = self::$attivo;
        self::$attivo = false;

        try {
            return $operazione();
        } finally {
            self::$attivo = $precedente;
        }
    }

    /** Registra un'operazione che non passa da un evento del modello (relazioni, importazioni, generazioni). */
    public static function registra(string $entita, int $id, string $azione, ?array $prima = null, ?array $dopo = null, ?string $etichetta = null, ?int $utenteId = null): void
    {
        if (! self::$attivo) {
            return;
        }

        self::query()->create([
            'user_id' => $utenteId ?? auth()->id(),
            'entita' => $entita, 'entita_id' => $id, 'etichetta' => $etichetta, 'azione' => $azione,
            'dati_prima' => $prima, 'dati_dopo' => $dopo,
        ]);
    }

    public const AZIONI = [
        'creazione' => 'Creazione', 'modifica' => 'Modifica', 'eliminazione' => 'Eliminazione', 'generazione' => 'Generazione',
        'importazione' => 'Importazione', 'duplicazione' => 'Duplicazione', 'cambio_stato' => 'Cambio di stato',
        'spostamento' => 'Spostamento', 'scambio' => 'Scambio', 'cambio_cattedra' => 'Cambio di docente/materia',
        'blocco' => 'Blocco/sblocco', 'cambio_aula' => 'Cambio di aula', 'annullamento' => 'Annullamento', 'ripristino' => 'Ripristino',
    ];

    public function etichettaAzione(): string
    {
        return self::AZIONI[$this->azione] ?? $this->azione;
    }

    /** Righe leggibili del dettaglio: "campo: prima → dopo" per le modifiche, "campo: valore" per il resto. */
    public function dettaglio(): array
    {
        $formatta = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (is_bool($v) ? ($v ? 'sì' : 'no') : ($v === null ? '—' : (string) $v));
        $prima = (array) $this->dati_prima;
        $dopo = (array) $this->dati_dopo;

        if ($this->azione === 'modifica' && $prima && $dopo) {
            return collect($dopo)->map(fn ($v, $k) => "{$k}: ".$formatta($prima[$k] ?? null).' → '.$formatta($v))->values()->all();
        }

        return collect($dopo ?: $prima)->map(fn ($v, $k) => "{$k}: ".$formatta($v))->values()->all();
    }
}
