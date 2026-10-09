<?php

namespace App\Services;

use App\Http\Requests\AulaRequest;
use App\Http\Requests\CattedraRequest;
use App\Http\Requests\ClasseRequest;
use App\Http\Requests\DisciplinaRequest;
use App\Http\Requests\DocenteRequest;
use App\Http\Requests\QuadroOrarioRequest;
use App\Http\Requests\ScansioneOrariaRequest;
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
    /**
     * Scansione oraria e quadri orari non hanno modello/request per riga: si importano con metodi propri (`nota` li descrive
     * nella pagina di import).
     *
     * @var array<string, array{titolo: string, modello?: class-string, request?: class-string, permesso: string, colonne: list<string>, nota?: string}>
     */
    public const LISTE = [
        'sedi' => ['titolo' => 'Sedi', 'modello' => Sede::class, 'request' => SedeRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['nome', 'indirizzo']],
        'aule' => ['titolo' => 'Aule', 'modello' => Aula::class, 'request' => AulaRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['nome', 'tipo', 'capienza', 'piano']],
        'discipline' => ['titolo' => 'Discipline', 'modello' => Disciplina::class, 'request' => DisciplinaRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['codice', 'nome', 'classe_concorso', 'tipo_aula_richiesto', 'padre', 'senza_slot', 'altre_aule']],
        'docenti' => ['titolo' => 'Docenti', 'modello' => Docente::class, 'request' => DocenteRequest::class, 'permesso' => 'gestisci-docenti-classi',
            'colonne' => ['nome', 'cognome', 'email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe', 'classi_concorso']],
        'classi' => ['titolo' => 'Classi', 'modello' => Classe::class, 'request' => ClasseRequest::class, 'permesso' => 'gestisci-docenti-classi',
            'colonne' => ['anno_corso', 'sezione', 'aula_base', 'quadro_orario', 'tempo_scuola', 'n_alunni', 'piano', 'conteggio_sostegno', 'slot_attivi']],
        'cattedre' => ['titolo' => 'Cattedre', 'modello' => Cattedra::class, 'request' => CattedraRequest::class, 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['docente_cognome', 'docente_nome', 'classe_anno', 'classe_sezione', 'disciplina', 'ore', 'compresenza', 'docente_clil_cognome', 'docente_clil_nome', 'ore_clil']],
        'scansione' => ['titolo' => 'Scansione oraria', 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['ora', 'inizio', 'fine', 'ricreazione_minuti', 'nome_pausa', 'pausa_prima_minuti', 'pausa_prima_nome', 'conteggio_pausa', 'pausa_prima_conteggio', 'aula_pausa', 'pausa_prima_aula'],
            'nota' => 'Il file sostituisce orari e ricreazioni di tutte le ore, uguali per tutti i giorni: deve quindi contenere tutte le ore della scansione. La pausa prima della prima ora (colonne pausa_prima_minuti e pausa_prima_nome) si scrive sulla riga della prima ora. Le colonne conteggio_pausa e pausa_prima_conteggio (facoltative, multipli di 15) sono i minuti con cui la pausa conta per il docente che la sorveglia; aula_pausa e pausa_prima_aula (facoltative) il nome di un\'aula di tipo «pausa». Se c\'è un errore non cambia nulla.'],
        'quadri-orari' => ['titolo' => 'Quadri orari', 'permesso' => 'gestisci-anagrafica',
            'colonne' => ['quadro', 'ore_totali', 'disciplina', 'ore_settimanali'],
            'nota' => 'Una riga per disciplina del quadro (la disciplina si scrive con il codice, quindi importa prima le discipline). Ogni quadro è importato per intero o per niente; quelli già presenti con lo stesso nome vengono saltati. Le ore totali si ricalcolano dalle righe: la colonna ore_totali è ignorata.'],
        ...ListeCsvAggiuntive::LISTE,
    ];

    /** Righe da esportare, nell'ordine di `colonne`. */
    public function righe(string $lista): iterable
    {
        if (isset(ListeCsvAggiuntive::LISTE[$lista])) {
            return app(ListeCsvAggiuntive::class)->righe($lista);
        }

        return match ($lista) {
            'sedi' => Sede::query()->orderBy('nome')->get()->map(fn ($s) => [$s->nome, $s->indirizzo]),
            'aule' => Aula::query()->orderBy('nome')->get()->map(fn ($a) => [$a->nome, $a->tipo, $a->capienza, $a->piano]),
            'discipline' => Disciplina::query()->with('padre')->orderBy('codice')->get()
                ->map(fn ($d) => [$d->codice, $d->nome, $d->classe_concorso, $d->tipo_aula_richiesto, $d->padre?->codice, (int) $d->senza_slot, implode('|', $d->tipi_aula_extra ?? [])]),
            'docenti' => Docente::query()->with('classiConcorso')->orderBy('cognome')->orderBy('nome')->get()
                ->map(fn ($d) => [$d->nome, $d->cognome, $d->email, $d->tipo_contratto, $d->tipo_posto, $d->regime, $d->ore_dovute, (int) $d->coe, $d->classiConcorso->pluck('classe_concorso')->implode('|')]),
            'classi' => Classe::query()->with('aulaBase', 'quadroOrario', 'slotAttivi')->orderBy('anno_corso')->orderBy('sezione')->get()
                ->map(fn ($c) => [$c->anno_corso, $c->sezione, $c->aulaBase?->nome, $c->quadroOrario->nome, $c->tempo_scuola, $c->n_alunni, $c->piano, $c->conteggio_sostegno,
                    $c->slotAttivi->sortBy(fn ($s) => $s->giorno * 100 + $s->ordine)->map(fn ($s) => (Slot::GIORNI_BREVI[$s->giorno] ?? $s->giorno).'.'.$s->ordine)->implode('|')]),
            // La scansione è uguale in tutti i giorni: si esporta una riga per ora, dai valori del primo giorno.
            'scansione' => Slot::query()->orderBy('ordine')->orderBy('giorno')->get()->groupBy('ordine')
                ->map(fn ($s, $ordine) => [$s->first()->ordine, substr($s->first()->inizio, 0, 5), substr($s->first()->fine, 0, 5), $s->first()->ricreazione_minuti, $s->first()->ricreazione_nome]
                    + [5 => $s->first()->pausa_prima_minuti, 6 => $s->first()->pausa_prima_nome, 7 => $s->first()->ricreazione_conteggio, 8 => $s->first()->pausa_prima_conteggio, 9 => $s->first()->ricreazioneAula?->nome, 10 => $s->first()->pausaPrimaAula?->nome]),   // la pausa prima della prima ora sta sulla riga della prima ora
            // Una riga per disciplina del quadro (un quadro senza righe compare con le ultime due colonne vuote).
            'quadri-orari' => QuadroOrario::query()->with('righe.disciplina')->orderBy('nome')->get()
                ->flatMap(fn ($q) => $q->righe->isEmpty() ? [[$q->nome, $q->ore_totali, null, null]]
                    : $q->righe->sortBy('disciplina.codice')->map(fn ($r) => [$q->nome, $q->ore_totali, $r->disciplina->codice, $r->ore_settimanali])),
            'cattedre' => Cattedra::query()->with('docente', 'classe', 'disciplina', 'docenteClil')->get()
                ->sortBy(fn ($c) => $c->classe->nomeCompleto().$c->docente->nomeCompleto())
                ->map(fn ($c) => [$c->docente->cognome, $c->docente->nome, $c->classe->anno_corso, $c->classe->sezione,
                    $c->disciplina->codice, $c->ore, (int) $c->compresenza, $c->docenteClil?->cognome, $c->docenteClil?->nome, $c->ore_clil]),
        };
    }

    /** @return array{importate: int, saltate: int, errori: list<string>} */
    public function importa(string $lista, string $percorso): array
    {
        $esito = ['importate' => 0, 'saltate' => 0, 'errori' => []];
        $righe = $this->leggiFile($lista, $percorso, $esito);
        if ($righe === null) {
            return $esito;
        }

        match ($lista) {
            'scansione' => $this->importaScansione($righe, $esito),
            'quadri-orari' => $this->importaQuadri($righe, $esito),
            'indisponibilita', 'sospensioni', 'assistenze-pausa', 'laboratori', 'impostazioni' => app(ListeCsvAggiuntive::class)->importa($lista, $righe, $esito),
            default => $this->importaRighe($lista, $righe, $esito),
        };

        return $esito;
    }

    /**
     * Righe del file come [numero di riga => [colonna => valore|null]]; null (con l'errore in $esito) se mancano colonne.
     *
     * @return array<int, array<string, string|null>>|null
     */
    private function leggiFile(string $lista, string $percorso, array &$esito): ?array
    {
        $f = fopen($percorso, 'r');
        $prima = (string) fgets($f);
        rewind($f);
        // Excel italiano salva con il punto e virgola.
        $sep = substr_count($prima, ';') > substr_count($prima, ',') ? ';' : ',';
        $intestazione = array_map(fn ($c) => trim($c, " \t\n\r\0\x0B\xEF\xBB\xBF"), fgetcsv($f, separator: $sep, escape: '\\') ?: []);

        $mancanti = array_diff(self::LISTE[$lista]['colonne'], $intestazione);
        if ($lista === 'docenti') {
            $mancanti = array_diff($mancanti, ['email', 'tipo_contratto', 'tipo_posto', 'regime', 'ore_dovute', 'coe', 'classi_concorso']);
        }
        if ($lista === 'discipline') {
            $mancanti = array_diff($mancanti, ['senza_slot', 'altre_aule']); // facoltativa (1 = non occupa un'ora di lezione, es. mensa): i file più vecchi non l'hanno
        }
        if (in_array($lista, ['aule', 'classi'], true)) {
            $mancanti = array_diff($mancanti, ['piano', 'conteggio_sostegno', 'slot_attivi']); // facoltativa: i file più vecchi non l'hanno
        }
        if ($lista === 'cattedre') {
            $mancanti = array_diff($mancanti, ['docente_clil_cognome', 'docente_clil_nome', 'ore_clil']); // facoltative
        }
        if ($lista === 'scansione') {
            $mancanti = array_diff($mancanti, ['nome_pausa', 'pausa_prima_minuti', 'pausa_prima_nome', 'conteggio_pausa', 'pausa_prima_conteggio', 'aula_pausa', 'pausa_prima_aula']); // facoltative: i file più vecchi non le hanno
        }
        if ($lista === 'quadri-orari') {
            $mancanti = array_diff($mancanti, ['ore_totali']);
        }
        if ($mancanti) {
            fclose($f);
            $esito['errori'][] = 'Colonne mancanti nell\'intestazione: '.implode(', ', $mancanti).'.';

            return null;
        }

        $righe = [];
        for ($n = 2; ($valori = fgetcsv($f, separator: $sep, escape: '\\')) !== false; $n++) {
            if ($valori !== [null]) { // salta le righe vuote
                $righe[$n] = array_map(fn ($v) => trim((string) $v) === '' ? null : trim($v),
                    array_combine($intestazione, array_pad(array_slice($valori, 0, count($intestazione)), count($intestazione), null)));
            }
        }
        fclose($f);

        return $righe;
    }

    private function importaRighe(string $lista, array $righe, array &$esito): void
    {
        $def = self::LISTE[$lista];

        DB::transaction(function () use ($righe, $lista, $def, &$esito) {
            foreach ($righe as $n => $riga) {
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
                    // Senza slot_attivi la classe parte dalle ore del mattino; altrimenti gli slot del file («LUN.1|LUN.2|…»).
                    try {
                        $modello->slotAttivi()->sync(filled($riga['slot_attivi'] ?? null)
                            ? app(ListeCsvAggiuntive::class)->slotDaTesto($riga['slot_attivi'])
                            : Slot::query()->where('ordine', '<=', Slot::ULTIMA_ORA_MATTINA)->pluck('id'));
                    } catch (InvalidArgumentException $e) {
                        $modello->slotAttivi()->sync(Slot::query()->where('ordine', '<=', Slot::ULTIMA_ORA_MATTINA)->pluck('id'));
                        $esito['errori'][] = "Riga {$n}: classe importata con le ore del mattino, ".$e->getMessage();
                    }
                }
                if ($modello instanceof Docente) {
                    foreach ($dati['classi_concorso'] ?? [] as $cc) {
                        $modello->classiConcorso()->create(['classe_concorso' => $cc]);
                    }
                }
                $esito['importate']++;
            }
        });
    }

    /** Tutto o niente: la scansione è unica per l'istituto e le regole (orari coerenti, ricreazioni) valgono sull'insieme delle ore. */
    private function importaScansione(array $righe, array &$esito): void
    {
        $ore = [];
        $righeOra = [];
        $paus = \App\Models\Aula::query()->where('tipo', \App\Enums\TipoAula::Pausa->value)->pluck('id', 'nome');
        $aulaPausa = function (int $n, ?string $nome) use ($paus, &$esito) {
            if (! filled($nome)) {
                return null;
            }
            if (! isset($paus[$nome])) {
                $esito['errori'][] = "Riga {$n}: «{$nome}» non è un'aula di tipo «pausa» censita.";
            }

            return $paus[$nome] ?? null;
        };
        foreach ($righe as $n => $r) {
            $ora = (int) $r['ora'];
            if (! ctype_digit((string) $r['ora']) || isset($ore[$ora])) {
                $esito['errori'][] = "Riga {$n}: ora «{$r['ora']}» non valida o ripetuta.";

                continue;
            }
            $righeOra[$ora] = $r;
            $ore[$ora] = [
                'inizio' => preg_replace('/^(\d):/', '0$1:', (string) $r['inizio']), 'fine' => preg_replace('/^(\d):/', '0$1:', (string) $r['fine']),
                'ricreazione' => $r['ricreazione_minuti'], 'nome' => $r['nome_pausa'] ?? null, 'conteggio' => $r['conteggio_pausa'] ?? null, 'aula' => $aulaPausa($n, $r['aula_pausa'] ?? null),
            ];
        }
        if ($esito['errori']) {
            return;
        }

        $esistenti = Slot::query()->distinct()->orderBy('ordine')->pluck('ordine')->all();
        ksort($ore);
        if (array_keys($ore) !== $esistenti) {
            $esito['errori'][] = 'Il file deve contenere tutte le ore della scansione (da '.reset($esistenti).' a '.end($esistenti).', una riga ciascuna): ha '.implode(', ', array_keys($ore)).'.';

            return;
        }

        // La pausa prima della prima ora sta sulla riga della prima ora.
        $prima = $righeOra[array_key_first($ore)];
        $pausaPrima = ['minuti' => $prima['pausa_prima_minuti'] ?? null, 'nome' => $prima['pausa_prima_nome'] ?? null, 'conteggio' => $prima['pausa_prima_conteggio'] ?? null, 'aula' => $aulaPausa(array_key_first($ore), $prima['pausa_prima_aula'] ?? null)];
if ($esito['errori']) {
            return;
        }

        $richiesta = new ScansioneOrariaRequest;
        $richiesta->merge(['ore' => $ore, 'pausa_prima' => $pausaPrima]);
        $validatore = Validator::make($richiesta->all(), $richiesta->rules());
        $richiesta->withValidator($validatore);
        if ($validatore->fails()) {
            $esito['errori'] = $validatore->errors()->all();

            return;
        }

        app(ScansioneOraria::class)->applica($ore, $pausaPrima);
        $esito['importate'] = count($ore);
    }

    /** Un quadro per volta, per intero o per niente. */
    private function importaQuadri(array $righe, array &$esito): void
    {
        $quadri = [];
        foreach ($righe as $n => $r) {
            if (! $r['quadro']) {
                $esito['errori'][] = "Riga {$n}: manca il nome del quadro.";

                continue;
            }
            $quadri[$r['quadro']][$n] = $r;
        }

        DB::transaction(function () use ($quadri, &$esito) {
            foreach ($quadri as $nome => $righeQuadro) {
                if (QuadroOrario::query()->where('nome', $nome)->exists()) {
                    $esito['saltate'] += count($righeQuadro);

                    continue;
                }

                $voci = [];
                $errori = [];
                foreach ($righeQuadro as $n => $r) {
                    if ($r['disciplina'] === null && $r['ore_settimanali'] === null) {
                        continue; // quadro senza righe
                    }
                    try {
                        $voci[] = ['disciplina_id' => $this->trova(Disciplina::query()->where('codice', $r['disciplina']), "disciplina «{$r['disciplina']}»"),
                            'ore_settimanali' => $r['ore_settimanali']];
                    } catch (InvalidArgumentException $e) {
                        $errori[] = "Riga {$n}: ".$e->getMessage();
                    }
                }
                if (! $errori) {
                    $richiesta = new QuadroOrarioRequest;
                    $messaggi = Validator::make(['nome' => $nome, 'righe' => $voci], $richiesta->rules())->errors()->all();
                    $errori = array_map(fn ($m) => "Quadro «{$nome}»: {$m}", array_unique($messaggi));
                }
                if ($errori) {
                    array_push($esito['errori'], ...$errori);

                    continue;
                }

                $quadro = QuadroOrario::query()->create(['nome' => $nome]);
                foreach ($voci as $voce) {
                    $quadro->righe()->create($voce);
                }
                $quadro->update(['ore_totali' => $quadro->righe()->sum('ore_settimanali')]);
                $esito['importate'] += count($righeQuadro);
            }
        });
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
                // La sede è quella corrente (la assegna il modello).
                return [['nome' => $r['nome']],
                    ['nome' => $r['nome'], 'tipo' => $r['tipo'], 'capienza' => $r['capienza'], 'piano' => $r['piano'] ?? null]];
            case 'discipline':
                $padre = $r['padre'] ? $this->trova(Disciplina::query()->where('codice', $r['padre']), "disciplina padre «{$r['padre']}»") : null;

                return [['codice' => $r['codice']], ['codice' => $r['codice'], 'nome' => $r['nome'], 'classe_concorso' => $r['classe_concorso'],
                    'tipo_aula_richiesto' => $r['tipo_aula_richiesto'], 'padre_id' => $padre, 'senza_slot' => (int) ($r['senza_slot'] ?? 0),
                    'tipi_aula_extra' => array_values(array_filter(array_map('trim', explode('|', (string) ($r['altre_aule'] ?? '')))))]];
            case 'docenti':
                return [['nome' => $r['nome'], 'cognome' => $r['cognome']], [
                    'nome' => $r['nome'], 'cognome' => $r['cognome'], 'email' => $r['email'] ?? null,
                    'tipo_contratto' => $r['tipo_contratto'] ?? 'tempo_indeterminato', 'tipo_posto' => $r['tipo_posto'] ?? 'comune',
                    'regime' => $r['regime'] ?? 'tempo_pieno', 'ore_dovute' => $r['ore_dovute'] ?? 18, 'coe' => $r['coe'] ?? 0,
                    'classi_concorso' => array_values(array_filter(array_map('trim', explode('|', (string) ($r['classi_concorso'] ?? ''))))) ]];
            case 'classi':
                // Come in passato, il quadro orario vuoto ripiega sul primo censito (della sede corrente).
                $quadro = $r['quadro_orario'] ? $this->trova(QuadroOrario::query()->where('nome', $r['quadro_orario']), "quadro orario «{$r['quadro_orario']}»") : QuadroOrario::query()->value('id');
                $aula = $r['aula_base'] ? $this->trova(Aula::query()->where('nome', $r['aula_base']), "aula base «{$r['aula_base']}»") : null;

                return [['anno_corso' => $r['anno_corso'], 'sezione' => $r['sezione']], [
                    'anno_corso' => $r['anno_corso'], 'sezione' => $r['sezione'], 'aula_base_id' => $aula,
                    'quadro_orario_id' => $quadro, 'tempo_scuola' => $r['tempo_scuola'] ?? 'normale', 'n_alunni' => $r['n_alunni'] ?? 0, 'piano' => $r['piano'] ?? null,
                    'conteggio_sostegno' => $r['conteggio_sostegno'] ?? null]];
            case 'cattedre':
                $clil = ($r['docente_clil_cognome'] ?? null) ? $this->trova(Docente::query()->where('cognome', $r['docente_clil_cognome'])->where('nome', $r['docente_clil_nome'] ?? ''), "docente CLIL {$r['docente_clil_cognome']} {$r['docente_clil_nome']}") : null;
                $docente = $this->trova(Docente::query()->where('cognome', $r['docente_cognome'])->where('nome', $r['docente_nome']), "docente {$r['docente_cognome']} {$r['docente_nome']}");
                $classe = $this->trova(Classe::query()->where('anno_corso', $r['classe_anno'])->where('sezione', $r['classe_sezione']), "classe {$r['classe_anno']}{$r['classe_sezione']}");
                $disciplina = $this->trova(Disciplina::query()->where('codice', $r['disciplina']), "disciplina «{$r['disciplina']}»");

                return [['docente_id' => $docente, 'classe_id' => $classe, 'disciplina_id' => $disciplina],
                    ['docente_id' => $docente, 'classe_id' => $classe, 'disciplina_id' => $disciplina, 'ore' => $r['ore'], 'compresenza' => $r['compresenza'] ?? 0,
                        'docente_clil_id' => $clil, 'ore_clil' => $clil ? ($r['ore_clil'] ?? 0) : 0]];
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
