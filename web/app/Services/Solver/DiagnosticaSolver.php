<?php

namespace App\Services\Solver;

use App\Constraints\Catalogo;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Vincolo;

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
        return collect($righe)->flatMap(fn (string $testo) => $this->righe($testo, $mappaLezioni))->values()->all();
    }

    /**
     * Una riga del solver può diventare più righe: «Conflitto tra vincoli: 5, 22» elenca i vincoli con la loro descrizione e il link per modificarli;
     * «Vincolo 22 ristretto a docenti: 41» dice a chi si riferisce il conflitto.
     *
     * @return list<array{testo: string, url: string|null}>
     */
    private function righe(string $testo, array $mappaLezioni): array
    {
        if (preg_match('/^Conflitto tra vincoli: ([\d, ]+)$/', $testo, $m)) {
            $ids = array_map('intval', preg_split('/\s*,\s*/', trim($m[1])));
            $vincoli = Vincolo::query()->whereIn('id', $ids)->get()->keyBy('id');
            $righe = [['testo' => 'Questi vincoli rigidi non possono valere tutti insieme: per generare l\'orario ammorbidiscine o toglinene almeno uno.', 'url' => route('vincoli.index')]];
            foreach ($ids as $id) {
                $v = $vincoli->get($id);
                $righe[] = $v
                    ? ['testo' => "Vincolo #{$id} · ".Catalogo::istanza($v->tipo)->etichetta().' · '.Catalogo::istanza($v->tipo)->descrizione($v->parametri ?? []).' (ambito: '.$v->ambito_livello.')', 'url' => route('vincoli.edit', $v)]
                    : ['testo' => "Vincolo #{$id} (non esiste più)", 'url' => null];
            }

            return $righe;
        }
        if (preg_match('/^Vincolo (\d+) ristretto a (docenti|classi): ([\d, ]+)$/', $testo, $m)) {
            $ids = array_map('intval', preg_split('/\s*,\s*/', trim($m[3])));
            $nomi = $m[2] === 'docenti'
                ? Docente::query()->whereIn('id', $ids)->orderBy('cognome')->get()->map(fn ($d) => $d->nomeCompleto())
                : Classe::query()->whereIn('id', $ids)->get()->map(fn ($c) => $c->nomeCompleto());

            return [['testo' => "Nel vincolo #{$m[1]} il conflitto riguarda: ".$nomi->implode(', ').'.', 'url' => $m[2] === 'docenti' ? route('docenti.edit', $ids[0]) : route('classi.edit', $ids[0])]];
        }
        if (str_starts_with($testo, 'Anche senza i vincoli configurati') || str_starts_with($testo, 'Analisi dell\'infattibilità interrotta')) {
            return [['testo' => $testo, 'url' => str_starts_with($testo, 'Anche') ? route('dashboard') : route('generazioni.create')]];
        }

        return [['testo' => $testo, 'url' => $this->url($testo, $mappaLezioni)]];
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
