<?php

namespace App\Support;

use App\Models\Disciplina;

/**
 * Un colore stabile per ogni disciplina (sfondo chiaro e testo scuro), uguale a schermo e nei PDF: si assegna in ordine
 * di codice, quindi non cambia tra una pagina e l'altra finché l'elenco delle discipline è lo stesso.
 */
class ColoriDiscipline
{
    /** [sfondo, testo] */
    public const PALETTE = [
        ['#dbeafe', '#1e3a8a'], ['#dcfce7', '#14532d'], ['#fef9c3', '#713f12'], ['#fce7f3', '#831843'],
        ['#ede9fe', '#4c1d95'], ['#ffedd5', '#7c2d12'], ['#cffafe', '#164e63'], ['#fee2e2', '#7f1d1d'],
        ['#e0e7ff', '#312e81'], ['#ecfccb', '#365314'], ['#f5d0fe', '#701a75'], ['#e2e8f0', '#1e293b'],
    ];

    /** @return array<int, array{0: string, 1: string}> id disciplina => [sfondo, testo] */
    public static function mappa(): array
    {
        $mappa = [];
        foreach (Disciplina::query()->orderBy('codice')->pluck('id') as $i => $id) {
            $mappa[$id] = self::PALETTE[$i % count(self::PALETTE)];
        }

        return $mappa;
    }

    /** Stile inline per una cella o un riquadro. */
    public static function stile(array $colore): string
    {
        return "background-color: {$colore[0]}; color: {$colore[1]};";
    }
}
