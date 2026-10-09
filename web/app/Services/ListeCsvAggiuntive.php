<?php

namespace App\Services;

use App\Enums\TipoAula;
use App\Models\AssistenzaPausa;
use App\Models\Aula;
use App\Models\Classe;
use App\Models\Docente;
use App\Models\Impostazioni;
use App\Models\Laboratorio;
use App\Models\Slot;
use App\Models\Sospensione;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Liste CSV legate ai docenti, ai laboratori e alle impostazioni (indisponibilità, sospensioni, assistenza alle pause,
 * laboratori, impostazioni): come le altre liste (vedi ListeCsv) si importano creando, saltando le righe già presenti
 * e segnalando gli errori per riga. Giorni: LUN…SAB o 1…6; ore e slot: «LUN.7» = lunedì, 7ª ora; elenchi con «|».
 */
class ListeCsvAggiuntive
{
    public const LISTE = [
        'indisponibilita' => ['titolo' => 'Indisponibilità dei docenti', 'permesso' => 'gestisci-docenti-classi', 'ritorno' => 'docenti.index',
            'colonne' => ['docente_cognome', 'docente_nome', 'giorno', 'ora'],
            'nota' => 'Una riga per ora in cui il docente non è disponibile (giorno LUN…SAB o 1…6, ora = numero dell\'ora). Il docente si scrive con cognome e nome già censiti; le righe già presenti sono saltate.'],
        'sospensioni' => ['titolo' => 'Sospensioni dei docenti', 'permesso' => 'gestisci-docenti-classi', 'ritorno' => 'docenti.index',
            'colonne' => ['docente_cognome', 'docente_nome', 'dal', 'al', 'motivo', 'esclude_da_orario', 'note', 'supplenti'],
            'nota' => 'Date come 2026-10-01 o 01/10/2026 (al vuoto = fino a nuova comunicazione); motivo: sospensione, malattia, congedo, altro; esclude_da_orario 1/0; supplenti = «Cognome Nome» separati da «|». Una sospensione già presente (stesso docente, data di inizio e motivo) è saltata.'],
        'assistenze-pausa' => ['titolo' => 'Assistenza alle pause', 'permesso' => 'gestisci-anagrafica', 'ritorno' => 'docenti.index',
            'colonne' => ['docente_cognome', 'docente_nome', 'giorno', 'dopo_ora'],
            'nota' => 'Una riga per giorno e pausa sorvegliati. dopo_ora = numero dell\'ora che precede la pausa (0 = pausa prima della prima ora); la pausa deve esistere in Scansione oraria.'],
        'laboratori' => ['titolo' => 'Laboratori', 'permesso' => 'gestisci-anagrafica', 'ritorno' => 'laboratori.index',
            'colonne' => ['nome', 'aula', 'n_partecipanti', 'attivo', 'note', 'docenti', 'classi', 'slot'],
            'nota' => 'docenti = «Cognome Nome» separati da «|»; classi = per esempio «1A|2B»; slot = ore del pomeriggio come «LUN.7|MAR.7». Un laboratorio con lo stesso nome è saltato.'],
        'impostazioni' => ['titolo' => 'Impostazioni', 'permesso' => 'gestisci-anagrafica', 'ritorno' => 'impostazioni.index',
            'colonne' => ['conteggio_sostegno'],
            'nota' => 'Una riga sola con il conteggio predefinito del sostegno (per_alunno o per_classe): a differenza delle altre liste aggiorna il valore della sede.'],
    ];

    private ?Collection $docenti = null;

    // ----------------------------------------------------------------------------------------------- esportazione

    public function righe(string $lista): iterable
    {
        return match ($lista) {
            'indisponibilita' => Docente::query()->with('indisponibilita')->orderBy('cognome')->orderBy('nome')->get()
                ->flatMap(fn ($d) => $d->indisponibilita->sortBy(fn ($s) => $s->giorno * 100 + $s->ordine)->map(fn ($s) => [$d->cognome, $d->nome, $this->giorno($s->giorno), $s->ordine])),
            'sospensioni' => Sospensione::query()->with('docente', 'supplenti')->orderBy('dal')->get()
                ->map(fn ($s) => [$s->docente->cognome, $s->docente->nome, $s->dal->format('Y-m-d'), $s->al?->format('Y-m-d'), $s->motivo, (int) $s->esclude_da_orario, $s->note,
                    $s->supplenti->map(fn ($d) => $d->cognome.' '.$d->nome)->implode('|')]),
            'assistenze-pausa' => AssistenzaPausa::query()->with('docente')->orderBy('giorno')->orderBy('ordine')->get()
                ->map(fn ($a) => [$a->docente->cognome, $a->docente->nome, $this->giorno($a->giorno), $a->ordine]),
            'laboratori' => Laboratorio::query()->with('aula', 'docenti', 'classi', 'slot')->orderBy('nome')->get()
                ->map(fn ($l) => [$l->nome, $l->aula?->nome, $l->n_partecipanti, (int) $l->attivo, $l->note,
                    $l->docenti->map(fn ($d) => $d->cognome.' '.$d->nome)->implode('|'),
                    $l->classi->map(fn ($c) => $c->anno_corso.$c->sezione)->implode('|'),
                    $l->slot->sortBy(fn ($s) => $s->giorno * 100 + $s->ordine)->map(fn ($s) => $this->giorno($s->giorno).'.'.$s->ordine)->implode('|')]),
            'impostazioni' => [[Impostazioni::correnti()->conteggio_sostegno]],
        };
    }

    // ----------------------------------------------------------------------------------------------- importazione

    public function importa(string $lista, array $righe, array &$esito): void
    {
        DB::transaction(function () use ($lista, $righe, &$esito) {
            foreach ($righe as $n => $r) {
                try {
                    $presente = $this->{'importa'.str_replace('-', '', ucwords($lista, '-'))}($r);
                    $esito[$presente ? 'saltate' : 'importate']++;
                } catch (InvalidArgumentException $e) {
                    $esito['errori'][] = "Riga {$n}: ".$e->getMessage();
                }
            }
        });
    }

    /** @return bool true se già presente (saltata) */
    private function importaIndisponibilita(array $r): bool
    {
        $docente = $this->docente($r['docente_cognome'], $r['docente_nome']);
        $slot = $this->slot($r['giorno'], $r['ora']);
        if ($docente->indisponibilita()->whereKey($slot->id)->exists()) {
            return true;
        }
        $docente->indisponibilita()->attach($slot->id);

        return false;
    }

    private function importaSospensioni(array $r): bool
    {
        $docente = $this->docente($r['docente_cognome'], $r['docente_nome']);
        $dal = $this->data($r['dal'], 'dal');
        $al = $r['al'] ? $this->data($r['al'], 'al') : null;
        $dati = Validator::make(['motivo' => $r['motivo'], 'note' => $r['note']], [
            'motivo' => ['required', 'in:'.implode(',', array_keys(Sospensione::MOTIVI))], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ($dati->fails()) {
            throw new InvalidArgumentException($dati->errors()->first());
        }
        if ($al && $al->lt($dal)) {
            throw new InvalidArgumentException('la data «al» è precedente a «dal».');
        }
        if ($docente->sospensioni()->whereDate('dal', $dal)->where('motivo', $r['motivo'])->exists()) {
            return true;
        }
        $supplenti = collect(explode('|', (string) $r['supplenti']))->map('trim')->filter()->map(fn ($nome) => $this->docenteDaNome($nome)->id)->all();
        $sospensione = $docente->sospensioni()->create([
            'dal' => $dal, 'al' => $al, 'motivo' => $r['motivo'], 'esclude_da_orario' => (bool) ($r['esclude_da_orario'] ?? 0), 'note' => $r['note'],
        ]);
        $sospensione->supplenti()->sync($supplenti);

        return false;
    }

    private function importaAssistenzePausa(array $r): bool
    {
        $docente = $this->docente($r['docente_cognome'], $r['docente_nome']);
        $giorno = $this->numeroGiorno($r['giorno']);
        $ordine = (int) $r['dopo_ora'];
        if (! ctype_digit((string) $r['dopo_ora']) || ! app(AssistenzaPause::class)->pause()->has($ordine)) {
            throw new InvalidArgumentException("non esiste una pausa dopo l'ora «{$r['dopo_ora']}» in Scansione oraria.");
        }
        if ($docente->assistenzePausa()->where('giorno', $giorno)->where('ordine', $ordine)->exists()) {
            return true;
        }
        $docente->assistenzePausa()->create(['giorno' => $giorno, 'ordine' => $ordine]);

        return false;
    }

    private function importaLaboratori(array $r): bool
    {
        if (Laboratorio::query()->where('nome', $r['nome'])->exists()) {
            return true;
        }
        $aula = $r['aula'] ? $this->unico(Aula::query()->where('nome', $r['aula']), "aula «{$r['aula']}»") : null;
        $docenti = collect(explode('|', (string) $r['docenti']))->map('trim')->filter()->map(fn ($n) => $this->docenteDaNome($n)->id)->all();
        $classi = collect(explode('|', (string) $r['classi']))->map('trim')->filter()->map(fn ($c) => $this->classe($c))->all();
        $slot = $this->slotDaTesto((string) $r['slot']);

        $dati = [
            'nome' => $r['nome'], 'aula_id' => $aula, 'n_partecipanti' => $r['n_partecipanti'], 'attivo' => (bool) ($r['attivo'] ?? 1), 'note' => $r['note'],
            'docenti' => $docenti, 'classi' => $classi, 'slot_ids' => $slot,
        ];
        $errori = Validator::make($dati, (new \App\Http\Requests\LaboratorioRequest)->rules())->errors();
        if ($errori->isNotEmpty()) {
            throw new InvalidArgumentException($errori->first());
        }
        $laboratorio = Laboratorio::query()->create(collect($dati)->only(['nome', 'aula_id', 'n_partecipanti', 'attivo', 'note'])->all());
        $laboratorio->docenti()->sync($docenti);
        $laboratorio->classi()->sync($classi);
        $laboratorio->slot()->sync($slot);

        return false;
    }

    private function importaImpostazioni(array $r): bool
    {
        if (! in_array($r['conteggio_sostegno'], array_keys(Impostazioni::CONTEGGI), true)) {
            throw new InvalidArgumentException('il conteggio deve essere per_alunno o per_classe.');
        }
        Impostazioni::correnti()->update(['conteggio_sostegno' => $r['conteggio_sostegno']]);

        return false;
    }

    // --------------------------------------------------------------------------------------------------- aiuti

    /** «LUN.1|LUN.2» => id degli slot. */
    public function slotDaTesto(string $testo): array
    {
        return collect(explode('|', $testo))->map('trim')->filter()->map(function ($t) {
            [$g, $o] = array_pad(explode('.', $t, 2), 2, null);

            return $this->slot($g, $o)->id;
        })->all();
    }

    private function giorno(int $numero): string
    {
        return Slot::GIORNI_BREVI[$numero] ?? (string) $numero;
    }

    private function numeroGiorno(?string $valore): int
    {
        $v = mb_strtoupper(trim((string) $valore));
        $numero = ctype_digit($v) ? (int) $v : (array_search($v, Slot::GIORNI_BREVI, true) ?: array_search($v, array_map('mb_strtoupper', Slot::GIORNI), true));
        if (! $numero || ! isset(Slot::GIORNI[$numero])) {
            throw new InvalidArgumentException("giorno «{$valore}» non valido (LUN…SAB o 1…6).");
        }

        return (int) $numero;
    }

    private function slot(?string $giorno, ?string $ora): Slot
    {
        $slot = Slot::query()->where('giorno', $this->numeroGiorno($giorno))->where('ordine', (int) $ora)->first();

        return $slot ?? throw new InvalidArgumentException("l'ora {$giorno}.{$ora} non esiste in Scansione oraria.");
    }

    private function data(?string $valore, string $campo): Carbon
    {
        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $d = Carbon::createFromFormat($formato, (string) $valore);
            if ($d && $d->format($formato) === $valore) {
                return $d->startOfDay();
            }
        }

        throw new InvalidArgumentException("data «{$campo}» non valida: «{$valore}» (usa 2026-10-01 o 01/10/2026).");
    }

    private function docente(?string $cognome, ?string $nome): Docente
    {
        return $this->unico(Docente::query()->where('cognome', $cognome)->where('nome', $nome), "docente {$cognome} {$nome}", true);
    }

    /** «Cognome Nome» (il cognome può avere spazi). */
    private function docenteDaNome(string $nomeCompleto): Docente
    {
        $this->docenti ??= Docente::query()->get()->groupBy(fn ($d) => mb_strtolower($d->cognome.' '.$d->nome));
        $trovati = $this->docenti->get(mb_strtolower(preg_replace('/\s+/', ' ', $nomeCompleto)), collect());
        if ($trovati->count() !== 1) {
            throw new InvalidArgumentException($trovati->isEmpty() ? "docente «{$nomeCompleto}» non trovato." : "docente «{$nomeCompleto}» è ambiguo.");
        }

        return $trovati->first();
    }

    private function classe(string $nome): int
    {
        if (! preg_match('/^(\d)([A-Za-z]+)$/u', preg_replace('/[\sª°]/u', '', $nome), $m)) {
            throw new InvalidArgumentException("classe «{$nome}» non riconosciuta (es. 1A).");
        }

        return $this->unico(Classe::query()->where('anno_corso', (int) $m[1])->where('sezione', mb_strtoupper($m[2])), "classe «{$nome}»");
    }

    /** Id (o modello con $modello) dell'unico record trovato. */
    private function unico($query, string $cosa, bool $modello = false): int|Docente
    {
        $trovati = $query->get();
        if ($trovati->count() !== 1) {
            throw new InvalidArgumentException($trovati->isEmpty() ? "{$cosa} non trovato." : "{$cosa} è ambiguo (più corrispondenze).");
        }

        return $modello ? $trovati->first() : $trovati->first()->id;
    }
}
