<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cosa succede, oltre ai record scelti, se si eliminano: i record collegati che il database elimina a cascata e quelli che
 * perdono soltanto il collegamento. Si ricava dalle chiavi esterne, quindi segue le migrazioni; conta i dati reali.
 */
class ConseguenzeEliminazione
{
    /** Liste da cui si elimina => tabella. */
    public const TABELLE = ['docenti', 'classi', 'discipline', 'quadri_orari', 'aule', 'sedi', 'cattedre', 'vincoli', 'laboratori', 'orari', 'users'];

    /** tabella => [singolare, plurale]; le tabelle senza etichetta (collegamenti tecnici) non si elencano. */
    private const ETICHETTE = [
        'sedi' => ['sede', 'sedi'], 'docenti' => ['docente', 'docenti'], 'classi' => ['classe', 'classi'], 'discipline' => ['disciplina', 'discipline'],
        'quadri_orari' => ['quadro orario', 'quadri orari'], 'quadro_orario_righe' => ['riga di quadro orario', 'righe di quadri orari'],
        'aule' => ['aula', 'aule'], 'cattedre' => ['cattedra', 'cattedre'], 'vincoli' => ['vincolo', 'vincoli'], 'laboratori' => ['laboratorio', 'laboratori'],
        'orari' => ['orario', 'orari'], 'lezioni' => ['lezione degli orari', 'lezioni degli orari'], 'generazioni' => ['generazione', 'generazioni'],
        'compresenze_sostegno' => ['compresenza di sostegno', 'compresenze di sostegno'], 'modifiche_orario' => ['modifica registrata', 'modifiche registrate'],
        'avvisi_orario' => ['avviso dell\'orario', 'avvisi dell\'orario'], 'sospensioni' => ['sospensione', 'sospensioni'],
        'assistenze_pausa' => ['assistenza alle pause', 'assistenze alle pause'], 'assegnazioni_sostegno' => ['assegnazione di sostegno', 'assegnazioni di sostegno'],
        'fabbisogni_sostegno' => ['fabbisogno di sostegno', 'fabbisogni di sostegno'], 'slot' => ['ora della scansione oraria', 'ore della scansione oraria'],
        'impostazioni' => ['impostazioni della sede', 'impostazioni della sede'], 'users' => ['utenza', 'utenze'], 'profili_vincoli' => ['profilo di vincoli', 'profili di vincoli'],
    ];

    /**
     * @param  list<int>  $ids
     * @return array{cascata: list<string>, collegamenti: list<string>} es. «141 cattedre»
     */
    public function per(string $tabella, array $ids): array
    {
        $cascata = [];
        $collegamenti = [];
        $figliePer = $this->figlie();
        $this->visita($tabella, $ids, $figliePer, $cascata, $collegamenti, [$tabella => true]);

        return ['cascata' => $this->elenco($cascata), 'collegamenti' => $this->elenco($collegamenti)];
    }

    /** @return array<string, list<array{tabella: string, colonna: string, azione: string}>> tabella padre => chiavi esterne che puntano a lei */
    private function figlie(): array
    {
        $risultato = [];
        foreach (Schema::getTables() as $tabella) {
            foreach (Schema::getForeignKeys($tabella['name']) as $fk) {
                $risultato[$fk['foreign_table']][] = ['tabella' => $tabella['name'], 'colonna' => $fk['columns'][0], 'azione' => $fk['on_delete']];
            }
        }

        return $risultato;
    }

    private function visita(string $padre, array $ids, array $figlie, array &$cascata, array &$collegamenti, array $visitate): void
    {
        foreach ($figlie[$padre] ?? [] as $fk) {
            if (! in_array($fk['azione'], ['cascade', 'set null'], true) || $ids === []) {
                continue;
            }
            $query = DB::table($fk['tabella'])->whereIn($fk['colonna'], $ids);
            $ha_id = Schema::hasColumn($fk['tabella'], 'id');
            $trovati = $ha_id ? $query->pluck('id')->all() : [$query->count()];

            if ($fk['azione'] === 'set null') {
                $collegamenti[$fk['tabella']] = ($ha_id ? array_unique([...($collegamenti[$fk['tabella']] ?? []), ...$trovati]) : $trovati);

                continue;
            }
            $cascata[$fk['tabella']] = $ha_id ? array_unique([...($cascata[$fk['tabella']] ?? []), ...$trovati]) : $trovati;
            if ($ha_id && $trovati && ! isset($visitate[$fk['tabella']])) {
                $this->visita($fk['tabella'], $trovati, $figlie, $cascata, $collegamenti, $visitate + [$fk['tabella'] => true]);
            }
        }
    }

    /** @return list<string> «3 classi», solo per le tabelle con un'etichetta e con almeno un record */
    private function elenco(array $per): array
    {
        $righe = [];
        foreach ($per as $tabella => $trovati) {
            $n = array_key_exists(0, $trovati) && count($trovati) === 1 && ! Schema::hasColumn($tabella, 'id') ? $trovati[0] : count($trovati);
            if ($n > 0 && isset(self::ETICHETTE[$tabella])) {
                $righe[] = $n.' '.self::ETICHETTE[$tabella][$n === 1 ? 0 : 1];
            }
        }
        sort($righe, SORT_NATURAL);

        return $righe;
    }
}
