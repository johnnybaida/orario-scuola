<?php

namespace App\Services\Solver;

use App\Models\Cattedra;

/**
 * Le righe di diagnostica del solver sono testi: qui si associa a ciascuna, quando si riconosce, la pagina dove correggere
 * la causa (come per i controlli prima di generare). Chi non si riconosce resta un testo semplice (url null).
 */
class DiagnosticaSolver
{
    /**
     * @param  list<string>  $righe
     * @param  array<int, int>  $mappaLezioni  id lezione del solver => id cattedra
     * @return list<array{testo: string, url: string|null}>
     */
    public function conLink(array $righe, array $mappaLezioni): array
    {
        return array_map(fn (string $testo) => ['testo' => $testo, 'url' => $this->url($testo, $mappaLezioni)], $righe);
    }

    private function url(string $testo, array $mappaLezioni): ?string
    {
        // «Lezione 12 (disciplina ITA): nessuno slot disponibile…»: indisponibilità del docente o slot attivi della classe.
        if (preg_match('/^Lezione (\d+)\b.*nessuno slot disponibile/', $testo, $m) && isset($mappaLezioni[(int) $m[1]])) {
            $cattedra = Cattedra::query()->find($mappaLezioni[(int) $m[1]]);

            return $cattedra ? route('docenti.edit', $cattedra->docente_id) : null;
        }
        // «Lezione 12: richiede aula di tipo 'x' ma nessuna è censita.»
        if (preg_match('/^Lezione \d+: richiede aula di tipo/', $testo)) {
            return route('aule.index');
        }
        // «Sostegno classe 5: il docente 7 ha solo … slot disponibili…»
        if (preg_match('/^Sostegno classe (\d+):/', $testo, $m)) {
            return route('classi.edit', (int) $m[1]);
        }
        if (str_starts_with($testo, 'Nessuna soluzione soddisfa i vincoli rigidi')) {
            return route('vincoli.index');
        }
        if (str_starts_with($testo, 'Tempo limite raggiunto')) {
            return route('generazioni.create');
        }

        return null;
    }
}
