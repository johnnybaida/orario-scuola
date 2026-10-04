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
    public static function applica(HasMany $relazione, array $righe, array $campi): void
    {
        $righe = array_values($righe);
        $base = fn () => $relazione->getRelated()->newQuery()
            ->where($relazione->getForeignKeyName(), $relazione->getParentKey());

        // Per modello (non in blocco): così creazione, modifica ed eliminazione finiscono nell'audit log.
        $base()->whereNotIn('id', array_filter(array_column($righe, 'id')))->get()->each->delete();

        foreach ($righe as $riga) {
            $dati = [];
            foreach ($campi as $campo) {
                $dati[$campo] = $riga[$campo] ?? false;
            }
            empty($riga['id']) ? $relazione->create($dati) : $base()->whereKey($riga['id'])->first()?->update($dati);
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
