<?php

namespace App\Services;

use App\Models\Cattedra;
use App\Models\QuadroOrarioRiga;
use Illuminate\Support\Collection;

/**
 * Confronta le ore delle cattedre con quelle che il quadro orario della classe prevede per la stessa disciplina
 * (somma di tutte le cattedre della classe in quella disciplina, escluse quelle in compresenza), per la colonna
 * «Stato» dell'elenco cattedre.
 */
class StatoCattedre
{
    /**
     * @param  Collection<int, Cattedra>  $cattedre  con la classe caricata
     * @return array<int, array{etichetta: string, colore: string, titolo: string}> per id cattedra
     */
    public function per(Collection $cattedre): array
    {
        $somme = Cattedra::query()->whereIn('classe_id', $cattedre->pluck('classe_id')->unique())->where('compresenza', false)
            ->selectRaw('classe_id, disciplina_id, sum(ore) as ore')->groupBy('classe_id', 'disciplina_id')->get()
            ->mapWithKeys(fn ($r) => [$r->classe_id.'-'.$r->disciplina_id => (int) $r->ore]);
        $previste = QuadroOrarioRiga::query()->whereIn('quadro_orario_id', $cattedre->pluck('classe.quadro_orario_id')->unique())->get()
            ->mapWithKeys(fn ($r) => [$r->quadro_orario_id.'-'.$r->disciplina_id => (int) $r->ore_settimanali]);

        return $cattedre->mapWithKeys(function (Cattedra $c) use ($somme, $previste) {
            if ($c->compresenza) {
                return [$c->id => ['etichetta' => 'Compresenza', 'colore' => 'bg-gray-100 text-gray-700', 'titolo' => 'Cattedra in compresenza: non entra nel conteggio delle ore.']];
            }
            $assegnate = $somme[$c->classe_id.'-'.$c->disciplina_id] ?? 0;
            $prevista = $previste[$c->classe->quadro_orario_id.'-'.$c->disciplina_id] ?? null;
            $dove = "{$c->classe->nomeCompleto()}, {$c->disciplina->nome}";

            return [$c->id => match (true) {
                $prevista === null => ['etichetta' => 'Non nel quadro', 'colore' => 'bg-red-100 text-red-800', 'titolo' => "{$dove}: il quadro orario non prevede questa disciplina."],
                $assegnate === $prevista => ['etichetta' => 'OK', 'colore' => 'bg-green-100 text-green-800', 'titolo' => "{$dove}: {$assegnate} ore su {$prevista} del quadro."],
                $assegnate < $prevista => ['etichetta' => 'Mancano '.($prevista - $assegnate).' h', 'colore' => 'bg-amber-100 text-amber-800', 'titolo' => "{$dove}: cattedre per {$assegnate} ore, il quadro ne prevede {$prevista}."],
                default => ['etichetta' => ($assegnate - $prevista).' h in più', 'colore' => 'bg-red-100 text-red-800', 'titolo' => "{$dove}: cattedre per {$assegnate} ore, il quadro ne prevede {$prevista}."],
            }];
        })->all();
    }
}
