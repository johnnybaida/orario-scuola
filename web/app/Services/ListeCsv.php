<?php

namespace App\Services;

use App\Http\Requests\AulaRequest;
use App\Http\Requests\CattedraRequest;
use App\Http\Requests\ClasseRequest;
use App\Http\Requests\DisciplinaRequest;
use App\Http\Requests\DocenteRequest;
use App\Http\Requests\SedeRequest;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Esporta e importa in CSV le liste piatte dell'anagrafica. I riferimenti ad altre liste sono per nome o codice, mai per id,
 * così il file si legge in Excel e si porta da un'installazione all'altra. L'import crea soltanto: le righe già presenti
 * (stessa chiave) vengono saltate, quelle non valide segnalate con il numero di riga, le altre importate; la validazione
 * è quella dei form (le FormRequest).
 */
class ListeCsv
{
    /** @var array<string, array{titolo: string, modello: class-string, request: class-string, permesso: string, colonne: list<string>}> */
    public const LISTE = [
        'sedi' => ['titolo' => 'Sedi', 'modello' => Sede::class, 'request' => SedeRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['nome', 'indirizzo']],
        'aule' => ['titolo' => 'Aule', 'modello' => Aula::class, 'request' => AulaRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['sede', 'nome', 'tipo', 'capienza']],
        'discipline' => ['titolo' => 'Discipline', 'modello' => Disciplina::class, 'request' => DisciplinaRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['codice', 'nome', 'classe_concorso', 'tipo_aula_richiesto', 'padre']],
        'docenti' => ['titolo' => 'Docenti', 'modello' => Docente::class, 'request' => DocenteRequest::class, 'permesso' => 'gestisci-docenti-classi',
            'colonne' => ['nome', 'cognome', 'email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe']],
        'classi' => ['titolo' => 'Classi', 'modello' => Classe::class, 'request' => ClasseRequest::class, 'permesso' => 'gestisci-docenti-classi',
            'colonne' => ['anno_corso', 'sezione', 'sede', 'aula_base', 'quadro_orario', 'tempo_scuola', 'n_alunni']],
        'cattedre' => ['titolo' => 'Cattedre', 'modello' => Cattedra::class, 'request' => CattedraRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['docente_cognome', 'docente_nome', 'classe_anno', 'classe_sezione', 'classe_sede', 'disciplina', 'ore', 'compresenza']],
    ];

    /** Righe da esportare, nell'ordine di `colonne`. */
    public function righe(string $lista): iterable
    {
        return match ($lista) {
            'sedi' => Sede::query()->orderBy('nome')->get()->map(fn ($s) => [$s->nome, $s->indirizzo]),
            'aule' => Aula::query()->with('sede')->orderBy('nome')->get()->map(fn ($a) => [$a->sede->nome, $a->nome, $a->tipo, $a->capienza]),
            'discipline' => Disciplina::query()->with('padre')->orderBy('codice')->get()
                ->map(fn ($d) => [$d->codice, $d->nome, $d->classe_concorso, $d->tipo_aula_richiesto, $d->padre?->codice]),
            'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get()
                ->map(fn ($d) => [$d->nome, $d->cognome, $d->email, $d->tipo_contratto, $d->tipo_posto, $d->regime, $d->ore_dovute, (int) $d->coe]),
            'classi' => Classe::query()->with('sede', 'aulaBase', 'quadroOrario')->orderBy('anno_corso')->orderBy('sezione')->get()
                ->map(fn ($c) => [$c->anno_corso, $c->sezione, $c->sede->nome, $c->aulaBase?->nome, $c->quadroOrario->nome, $c->tempo_scuola, $c->n_alunni]),
            'cattedre' => Cattedra::query()->with('docente', 'classe.sede', 'disciplina')->get()
                ->sortBy(fn ($c) => $c->classe->nomeCompleto().$c->docente->nomeCompleto())
                ->map(fn ($c) => [$c->docente->cognome, $c->docente->nome, $c->classe->anno_corso, $c->classe->sezione, $c->classe->sede->nome,
                    $c->disciplina->codice, $c->ore, (int) $c->compresenza]),
        };
    }

    /** @return array{importate: int, saltate: int, errori: list<string>} */
    public function importa(string $lista, string $percorso): array
    {
        $def = self::LISTE[$lista];
        $esito = ['importate' => 0, 'saltate' => 0, 'errori' => []];

        $f = fopen($percorso, 'r');
        $prima = (string) fgets($f);
        rewind($f);
        // Excel italiano salva con il punto e virgola.
        $sep = substr_count($prima, ';') > substr_count($prima, ',') ? ';' : ',';
        $intestazione = array_map(fn ($c) => trim($c, " \t\n\r\0\x0B\xEF\xBB\xBF"), fgetcsv($f, separator: $sep, escape: '\\') ?: []);

        $mancanti = array_diff($def['colonne'], $intestazione);
        if ($lista === 'docenti') {
            $mancanti = array_diff($mancanti, ['email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe']);
        }
        if ($mancanti) {
            fclose($f);
            $esito['errori'][] = 'Colonne mancanti nell\'intestazione: '.implode(', ', $mancanti).'.';

            return $esito;
        }

        DB::transaction(function () use ($f, $sep, $intestazione, $lista, $def, &$esito) {
            for ($n = 2; ($valori = fgetcsv($f, separator: $sep, escape: '\\')) !== false; $n++) {
                if ($valori === [null]) {
                    continue; // riga vuota
                }
                $riga = array_map(fn ($v) => trim((string) $v) === '' ? null : trim($v), array_combine($intestazione, array_pad(array_slice($valori, 0, count($intestazione)), count($intestazione), null)));

                try {
                    [$chiave, $dati] = $this->leggi($lista, $riga);
                    if ($def['modello']::query()->where($chiave)->exists()) {
                        $esito['saltate']++;

                        continue;
                    }
                    $richiesta = new $def['request'];
                    $richiesta->merge($dati);
                    $errori = Validator::make($dati, $richiesta->rules())->errors();
                    if ($errori->isNotEmpty()) {
                        throw new InvalidArgumentException($errori->first());
                    }
                } catch (InvalidArgumentException $e) {
                    $esito['errori'][] = "Riga {$n}: ".$e->getMessage();

                    continue;
                }

                $modello = $def['modello']::query()->create($dati);
                if ($modello instanceof Classe) {
                    $modello->slotAttivi()->sync(Slot::query()->where('ordine', '<=', Slot::ULTIMA_ORA_MATTINA)->pluck('id'));
                }
                $esito['importate']++;
            }
        });
        fclose($f);

        return $esito;
    }

    /**
     * Da una riga del file agli attributi del modello, con i nomi risolti in id.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [chiave che identifica la riga, attributi]
     *
     * @throws InvalidArgumentException se un riferimento non esiste
     */
    private function leggi(string $lista, array $r): array
    {
        switch ($lista) {
            case 'sedi':
                return [['nome' => $r['nome']], ['nome' => $r['nome'], 'indirizzo' => $r['indirizzo']]];
            case 'aule':
                $sede = $this->trova(Sede::query()->where('nome', $r['sede']), "sede «{$r['sede']}»");

                return [['sede_id' => $sede, 'nome' => $r['nome']],
                    ['sede_id' => $sede, 'nome' => $r['nome'], 'tipo' => $r['tipo'], 'capienza' => $r['capienza']]];
            case 'discipline':
                $padre = $r['padre'] ? $this->trova(Disciplina::query()->where('codice', $r['padre']), "disciplina padre «{$r['padre']}»") : null;

                return [['codice' => $r['codice']], ['codice' => $r['codice'], 'nome' => $r['nome'], 'classe_concorso' => $r['classe_concorso'],
                    'tipo_aula_richiesto' => $r['tipo_aula_richiesto'], 'padre_id' => $padre]];
            case 'docenti':
                return [['nome' => $r['nome'], 'cognome' => $r['cognome']], [
                    'nome' => $r['nome'], 'cognome' => $r['cognome'], 'email' => $r['email'] ?? null,
                    'tipo_contratto' => $r['tipo_contratto'] ?? 'tempo_indeterminato', 'tipo_posto' => $r['tipo_posto'] ?? 'comune',
                    'regime' => $r['regime'] ?? 'tempo_pieno', 'ore_dovute' => $r['ore_dovute'] ?? 18, 'coe' => $r['coe'] ?? 0]];
            case 'classi':
                // Come in passato, sede e quadro orario vuoti ripiegano sul primo censito.
                $sede = $r['sede'] ? $this->trova(Sede::query()->where('nome', $r['sede']), "sede «{$r['sede']}»") : Sede::query()->value('id');
                $quadro = $r['quadro_orario'] ? $this->trova(QuadroOrario::query()->where('nome', $r['quadro_orario']), "quadro orario «{$r['quadro_orario']}»") : QuadroOrario::query()->value('id');
                $aula = $r['aula_base'] ? $this->trova(Aula::query()->where('nome', $r['aula_base'])->where('sede_id', $sede), "aula base «{$r['aula_base']}»") : null;

                return [['anno_corso' => $r['anno_corso'], 'sezione' => $r['sezione'], 'sede_id' => $sede], [
                    'anno_corso' => $r['anno_corso'], 'sezione' => $r['sezione'], 'sede_id' => $sede, 'aula_base_id' => $aula,
                    'quadro_orario_id' => $quadro, 'tempo_scuola' => $r['tempo_scuola'] ?? 'normale', 'n_alunni' => $r['n_alunni'] ?? 0]];
            case 'cattedre':
                $docente = $this->trova(Docente::query()->where('cognome', $r['docente_cognome'])->where('nome', $r['docente_nome']), "docente {$r['docente_cognome']} {$r['docente_nome']}");
                $classe = $this->trova(Classe::query()->where('anno_corso', $r['classe_anno'])->where('sezione', $r['classe_sezione'])
                    ->when($r['classe_sede'], fn ($q, $s) => $q->whereHas('sede', fn ($q) => $q->where('nome', $s))), "classe {$r['classe_anno']}{$r['classe_sezione']}");
                $disciplina = $this->trova(Disciplina::query()->where('codice', $r['disciplina']), "disciplina «{$r['disciplina']}»");

                return [['docente_id' => $docente, 'classe_id' => $classe, 'disciplina_id' => $disciplina],
                    ['docente_id' => $docente, 'classe_id' => $classe, 'disciplina_id' => $disciplina, 'ore' => $r['ore'], 'compresenza' => $r['compresenza'] ?? 0]];
        }
    }

    /** Id dell'unico record trovato; errore leggibile se manca o se è ambiguo. */
    private function trova($query, string $cosa): int
    {
        $ids = $query->pluck('id');
        if ($ids->count() !== 1) {
            throw new InvalidArgumentException($ids->isEmpty() ? "{$cosa} non trovata." : "{$cosa} è ambigua (più corrispondenze).");
        }

        return $ids->first();
    }
}
