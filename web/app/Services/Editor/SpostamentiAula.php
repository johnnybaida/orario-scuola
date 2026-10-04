<?php

namespace App\Services\Editor;

use App\Models\Aula;
use App\Models\Lezione;
use Illuminate\Support\Collection;

/**
 * Dove deve spostarsi una classe durante la giornata: tra due ore consecutive della stessa mattina o dello stesso
 * pomeriggio, la classe cambia aula se l'aula effettiva (quella assegnata o, in mancanza, la sua aula base) è diversa.
 * Con la didattica DADA succede a ogni cambio di materia; con le classi a aula fissa solo per palestra e laboratori.
 */
class SpostamentiAula
{
    /** L'aula in cui si svolge la lezione: quella assegnata o l'aula base della classe. Richiede cattedra.classe.aulaBase. */
    public static function aulaEffettiva(Lezione $lezione): ?Aula
    {
        return $lezione->aula ?? $lezione->cattedra->classe->aulaBase;
    }

    /**
     * @param  Collection<int, Lezione>  $lezioniDiUnaClasse  con slot, aula e cattedra.classe.aulaBase
     * @return array<int, array{da: Aula, a: Aula}> id lezione => cambio rispetto all'ora precedente
     */
    public function cambi(Collection $lezioniDiUnaClasse): array
    {
        $cambi = [];
        $ordinate = $lezioniDiUnaClasse->sortBy(fn (Lezione $l) => $l->slot->giorno * 100 + $l->slot->ordine)->values();

        foreach ($ordinate as $i => $lezione) {
            $prima = $ordinate[$i - 1] ?? null;
            if (! $prima || $prima->slot->giorno !== $lezione->slot->giorno || $prima->slot->ordine + 1 !== $lezione->slot->ordine) {
                continue;
            }
            $da = self::aulaEffettiva($prima);
            $a = self::aulaEffettiva($lezione);
            if ($da && $a && $da->id !== $a->id) {
                $cambi[$lezione->id] = ['da' => $da, 'a' => $a];
            }
        }

        return $cambi;
    }
}
