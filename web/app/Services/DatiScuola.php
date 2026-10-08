<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Generazione;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Esporta e importa i dati dell'applicazione: uno ZIP con un file JSON per tabella e un `manifest.json`.
 * Le tabelle sono quelle del database meno quelle di servizio (registro attività, utenze, sessioni, code, cache): l'elenco e
 * l'ordine (le tabelle da cui altre dipendono prima) si ricavano dalle chiavi esterne, quindi seguono le migrazioni.
 * L'import sostituisce le tabelle scelte in un'unica transazione e controlla che, alla fine, nessun riferimento
 * (chiave esterna) punti a righe inesistenti: se manca una tabella da cui altre dipendono, non cambia nulla.
 * Le utenze non si esportano: i riferimenti agli utenti (es. chi ha creato un orario) restano solo se l'utente esiste
 * anche qui, altrimenti diventano vuoti; i collegamenti delle utenze ai docenti si svuotano se il docente non c'è più.
 */
class DatiScuola
{
    private const ESCLUSE = ['audit_log', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'sessions', 'migrations', 'users'];

    /** @return list<string> tabelle esportabili, con quelle da cui altre dipendono prima */
    public function tabelle(): array
    {
        $da = array_diff(array_column(Schema::getTables(), 'name'), self::ESCLUSE);
        $dipende = [];
        foreach ($da as $t) {
            $dipende[$t] = array_values(array_diff(array_intersect(array_column(Schema::getForeignKeys($t), 'foreign_table'), $da), [$t]));
        }

        $ordinate = [];
        while ($dipende) {
            $pronte = array_keys(array_filter($dipende, fn ($padri) => ! array_diff($padri, $ordinate)));
            if (! $pronte) {
                throw new RuntimeException('Dipendenze circolari tra le tabelle: '.implode(', ', array_keys($dipende)));
            }
            sort($pronte);
            $ordinate = [...$ordinate, ...$pronte];
            $dipende = array_diff_key($dipende, array_flip($pronte));
        }

        return $ordinate;
    }

    /** @return string percorso dello ZIP temporaneo (da cancellare dopo l'invio) */
    public function esporta(array $scelte): string
    {
        $tabelle = array_values(array_intersect($this->tabelle(), $scelte));
        if (! $tabelle) {
            throw new RuntimeException('Scegli almeno una tabella.');
        }

        $percorso = tempnam(sys_get_temp_dir(), 'dati').'.zip';
        $zip = new ZipArchive;
        $zip->open($percorso, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $conteggi = [];
        foreach ($tabelle as $t) {
            $righe = DB::table($t)->get()->map(fn ($r) => (array) $r)->all();
            $conteggi[$t] = count($righe);
            $zip->addFromString("{$t}.json", json_encode($righe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        $zip->addFromString('manifest.json', json_encode([
            'formato' => 1, 'versione' => config('app.versione'), 'creato' => now()->toIso8601String(),
            'migrazioni' => DB::table('migrations')->pluck('migration')->all(), 'tabelle' => $conteggi,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $zip->close();

        AuditLog::registra('Dati', 0, 'esportazione', null, ['tabelle' => $tabelle]);

        return $percorso;
    }

    /**
     * Legge e controlla il manifest di uno ZIP esportato.
     *
     * @return array{versione: string, creato: string, tabelle: array<string, int>}
     *
     * @throws RuntimeException se il file non è valido o viene da una versione più recente
     */
    public function manifest(string $percorso): array
    {
        $zip = new ZipArchive;
        if ($zip->open($percorso) !== true || ($json = $zip->getFromName('manifest.json')) === false) {
            throw new RuntimeException('Il file non è un archivio di dati di Orario Scuola.');
        }
        $zip->close();

        $m = json_decode($json, true);
        if (! is_array($m) || ($m['formato'] ?? null) !== 1 || ! isset($m['tabelle'], $m['migrazioni'])) {
            throw new RuntimeException('Formato dell\'archivio non riconosciuto.');
        }
        $sconosciute = array_diff($m['migrazioni'], DB::table('migrations')->pluck('migration')->all());
        if ($sconosciute) {
            throw new RuntimeException("L'archivio viene da una versione più recente dell'applicazione (versione {$m['versione']}): aggiorna prima l'installazione.");
        }

        return ['versione' => $m['versione'] ?? '?', 'creato' => $m['creato'] ?? '', 'tabelle' => $m['tabelle']];
    }

    /**
     * Sostituisce con quelle dell'archivio le tabelle scelte.
     *
     * @return array{tabelle: list<string>, righe: int}
     *
     * @throws RuntimeException se qualcosa non va (in tal caso non cambia nulla)
     */
    public function importa(string $percorso, array $scelte): array
    {
        $manifest = $this->manifest($percorso);
        $note = $this->tabelle();
        $tabelle = array_values(array_intersect($note, $scelte));
        if (! $tabelle) {
            throw new RuntimeException('Scegli almeno una tabella da importare.');
        }
        if ($mancanti = array_diff($tabelle, array_keys($manifest['tabelle']))) {
            throw new RuntimeException('Nell\'archivio mancano: '.implode(', ', $mancanti).'.');
        }
        if (Generazione::query()->withoutGlobalScopes()->whereIn('stato', ['in_coda', 'in_corso'])->exists()) {
            throw new RuntimeException('C\'è una generazione in corso: aspetta che finisca (o annullala) e riprova.');
        }

        $zip = new ZipArchive;
        $zip->open($percorso);
        $dati = [];
        foreach ($tabelle as $t) {
            $dati[$t] = json_decode($zip->getFromName("{$t}.json"), true);
            $colonne = Schema::getColumnListing($t);
            if (! is_array($dati[$t]) || array_diff(array_keys($dati[$t][0] ?? []), $colonne)) {
                throw new RuntimeException("I dati di «{$t}» non sono compatibili con questa installazione.");
            }
        }
        $zip->close();

        // Riferimenti a tabelle non importabili (utenze): restano solo se la riga esiste in questa installazione.
        $esistenti = [];
        foreach ($tabelle as $t) {
            foreach (Schema::getForeignKeys($t) as $fk) {
                if (in_array($fk['foreign_table'], $note, true)) {
                    continue;
                }
                [$colonna, $padre] = [$fk['columns'][0], $fk['foreign_table']];
                $esistenti[$padre] ??= array_flip(DB::table($padre)->pluck($fk['foreign_columns'][0])->all());
                foreach ($dati[$t] as &$riga) {
                    if (($riga[$colonna] ?? null) !== null && ! isset($esistenti[$padre][$riga[$colonna]])) {
                        $riga[$colonna] = null;
                    }
                }
                unset($riga);
            }
        }

        // Con i controlli sulle chiavi disattivati (fuori dalla transazione: dentro è ignorato) l'ordine e le cancellazioni a cascata non contano;
        // la coerenza si verifica alla fine. ponytail: dipende da questo anche l'inserimento di righe che riferiscono righe della stessa tabella.
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tabelle, $dati) {
                foreach (array_reverse($tabelle) as $t) {
                    DB::table($t)->delete();
                }
                foreach ($tabelle as $t) {
                    foreach (array_chunk($dati[$t], 200) as $blocco) {
                        DB::table($t)->insert($blocco);
                    }
                }
                $this->verificaRiferimenti($tabelle);
                $this->svuotaOrfaneSetNull($tabelle);
            });
        } catch (QueryException $e) {
            throw new RuntimeException('Import non riuscito, nessun dato è stato cambiato: '.Str::before($e->getMessage(), ' (Connection'));
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $righe = array_sum(array_map('count', $dati));
        AuditLog::registra('Dati', 0, 'importazione', null, ['tabelle' => $tabelle, 'righe' => $righe, 'versione_archivio' => $manifest['versione']]);

        return ['tabelle' => $tabelle, 'righe' => $righe];
    }

    /** Come ON DELETE SET NULL: chi (anche fuori dall'import, es. le utenze) punta a righe sostituite e non più presenti perde il riferimento. */
    private function svuotaOrfaneSetNull(array $toccate): void
    {
        foreach (Schema::getTables() as $tabella) {
            if (in_array($tabella['name'], $toccate, true)) {
                continue;
            }
            foreach (Schema::getForeignKeys($tabella['name']) as $fk) {
                if ($fk['on_delete'] === 'set null' && in_array($fk['foreign_table'], $toccate, true)) {
                    $colonna = $fk['columns'][0];
                    DB::table($tabella['name'])->whereNotNull($colonna)
                        ->whereNotIn($colonna, DB::table($fk['foreign_table'])->select($fk['foreign_columns'][0]))->update([$colonna => null]);
                }
            }
        }
    }

    /** Ogni chiave esterna delle tabelle toccate (o che puntano a tabelle toccate) deve puntare a una riga esistente. */
    private function verificaRiferimenti(array $toccate): void
    {
        foreach ($this->tabelle() as $t) {
            foreach (Schema::getForeignKeys($t) as $fk) {
                if (! in_array($t, $toccate, true) && ! in_array($fk['foreign_table'], $toccate, true)) {
                    continue;
                }
                [$colonna, $colonnaPadre] = [$fk['columns'][0], $fk['foreign_columns'][0]];
                $orfane = DB::table($t)->whereNotNull($colonna)
                    ->whereNotIn($colonna, DB::table($fk['foreign_table'])->select($colonnaPadre))->exists();
                if ($orfane) {
                    throw new RuntimeException("Dati incoerenti: «{$t}» ({$colonna}) fa riferimento a righe di «{$fk['foreign_table']}» che non esistono. "
                        .'Importa insieme anche le tabelle collegate (o tutte). Non è stato cambiato nulla.');
                }
            }
        }
    }
}
