<?php

namespace App\Support;

use App\Models\Orario;
use App\Models\User;

/**
 * Ciclo di vita di un orario: bozza → in revisione → approvato → pubblicato → archiviato.
 * Solo la bozza è modificabile. Chi gestisce l'anagrafica prepara e invia in revisione; approvare, pubblicare
 * e archiviare spetta a chi ha il permesso `approva-orari` (amministratore e dirigente scolastico).
 */
class StatiOrario
{
    public const ETICHETTE = [
        'bozza' => 'Bozza',
        'in_revisione' => 'In revisione',
        'approvato' => 'Approvato',
        'pubblicato' => 'Pubblicato',
        'archiviato' => 'Archiviato',
    ];

    /** Testo del pulsante che porta a ciascuno stato. */
    public const AZIONI = [
        'in_revisione' => 'Invia in revisione',
        'approvato' => 'Approva',
        'pubblicato' => 'Pubblica',
        'archiviato' => 'Archivia',
        'bozza' => 'Riporta in bozza',
    ];

    private const TRANSIZIONI = [
        'bozza' => ['in_revisione'],
        'in_revisione' => ['approvato', 'bozza'],
        'approvato' => ['pubblicato', 'bozza'],
        'pubblicato' => ['archiviato'],
        'archiviato' => [],
    ];

    /** Stati in cui un orario si può eliminare. */
    public const ELIMINABILI = ['bozza', 'archiviato'];

    public static function transizioneValida(string $da, string $a): bool
    {
        return in_array($a, self::TRANSIZIONI[$da] ?? [], true);
    }

    /** Stati che l'utente può impostare partendo dallo stato attuale dell'orario. */
    public static function consentite(Orario $orario, User $utente): array
    {
        return array_values(array_filter(
            self::TRANSIZIONI[$orario->stato] ?? [],
            fn (string $a) => self::puo($utente, $orario->stato, $a),
        ));
    }

    public static function puo(User $utente, string $da, string $a): bool
    {
        return match (true) {
            $a === 'in_revisione' => $utente->can('gestisci-anagrafica'),
            // Rimandare in bozza una revisione può farlo anche il referente; riaprire un orario approvato solo chi approva.
            $a === 'bozza' && $da === 'in_revisione' => $utente->can('gestisci-anagrafica') || $utente->can('approva-orari'),
            default => $utente->can('approva-orari'),
        };
    }
}
