<?php

namespace App\Constraints;

use App\Models\Disciplina;

/**
 * Le discipline di un vincolo: `parametri.disciplina_ids` (elenco) o, nei vincoli salvati prima dell'elenco, `parametri.disciplina_id`.
 * Il vincolo vale per **ciascuna** disciplina scelta, come tante regole uguali (nessuna = qualsiasi, solo con ambito docente).
 */
class DisciplineVincolo
{
    /** @return list<int> */
    public static function ids(array $parametri): array
    {
        $ids = $parametri['disciplina_ids'] ?? (empty($parametri['disciplina_id']) ? [] : [$parametri['disciplina_id']]);

        return array_values(array_unique(array_map('intval', array_filter((array) $ids, fn ($i) => $i !== '' && $i !== null))));
    }

    /** I parametri con l'elenco al posto del singolo id (per salvare). */
    public static function normalizza(array $parametri): array
    {
        $ids = self::ids($parametri);
        unset($parametri['disciplina_id']);
        if ($ids || array_key_exists('disciplina_ids', $parametri)) {
            $parametri['disciplina_ids'] = $ids;
        }

        return $parametri;
    }

    /** «Arte e immagine, Musica» oppure $vuoto se l'elenco è vuoto. */
    public static function nomi(array $parametri, string $vuoto = 'Tutte le lezioni'): string
    {
        $ids = self::ids($parametri);

        return $ids ? Disciplina::query()->whereIn('id', $ids)->orderBy('nome')->pluck('nome')->implode(', ') : $vuoto;
    }
}
