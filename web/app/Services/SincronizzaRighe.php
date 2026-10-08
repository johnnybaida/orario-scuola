<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Applica a una relazione hasMany l'elenco di righe inviato da un form con righe ripetibili:
 * le righe con `id` si aggiornano, quelle senza si creano, quelle assenti si eliminano.
 * I campi mancanti (checkbox non spuntate) valgono false; i campi obbligatori sono già validati.
 */
class SincronizzaRighe
{
    /** @param callable(\Illuminate\Database\Eloquent\Model, array): void|null $dopo chiamata per ogni riga salvata (es. per sincronizzare una relazione collegata) */
    public static function applica(HasMany $relazione, array $righe, array $campi, ?callable $dopo = null): void
    {
        $righe = array_values($righe);
        $base = fn () => $relazione->getRelated()->newQuery()
            ->where($relazione->getForeignKeyName(), $relazione->getParentKey());

        // Per modello (non in blocco): così creazione, modifica ed eliminazione finiscono nell'audit log.
        $base()->whereNotIn('id', array_filter(array_column($righe, 'id')))->get()->each->delete();

        foreach ($righe as $riga) {
            $dati = [];
            foreach ($campi as $campo) {
                $dati[$campo] = array_key_exists($campo, $riga) ? $riga[$campo] : false; // null esplicito = NULL (es. data di fine aperta); campo assente = casella non spuntata
            }
            if (empty($riga['id'])) {
                $modello = $relazione->create($dati);
            } else {
                $modello = $base()->whereKey($riga['id'])->first();
                $modello?->update($dati);
            }
            if ($dopo && $modello) {
                $dopo($modello, $riga);
            }
        }
    }

    /** @throws ValidationException se due righe hanno la stessa combinazione di $campi */
    public static function controllaUnivoche(array $righe, array $campi, string $chiave, string $messaggio): void
    {
        $viste = array_map(fn ($r) => implode('-', Arr::only($r, $campi)), $righe);

        if (count($viste) !== count(array_unique($viste))) {
            throw ValidationException::withMessages([$chiave => $messaggio]);
        }
    }
}
