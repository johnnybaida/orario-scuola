<?php

namespace App\Services\Editor;

use App\Models\AuditLog;
use App\Models\Aula;
use App\Models\AvvisoOrario;
use App\Models\Cattedra;
use App\Models\Lezione;
use App\Models\ModificaOrario;
use App\Models\Orario;
use App\Models\Slot;

/**
 * Sposta (o scambia) una lezione nella griglia dell'orario, validando i
 * vincoli rigidi di sistema H1/H2/H5/H6/H7 rispetto alla sola lezione
 * toccata (non rilancia il solver completo: è un edit puntuale).
 *
 * ponytail: se la disciplina richiede un tipo di aula condiviso (palestra,
 * laboratorio...), verifichiamo solo che nello slot di destinazione ci sia
 * capienza libera per quel tipo, mantenendo l'aula già assegnata. Se la
 * scuola ha più aule dello stesso tipo e la capienza libera è su un'aula
 * diversa da quella attuale, serve poi una scelta esplicita dell'aula in UI
 * (non gestita qui).
 */
class EditorLezione
{
    /**
     * Sposta (o scambia) una lezione. Con $provvisorio i conflitti con docenti e aule (H2/H6/H3/H7) non bloccano:
     * lo spostamento avviene, il conflitto resta segnalato (avviso e Controllo orario) finché non lo si risolve.
     * Restano sempre bloccanti le lezioni bloccate e gli slot fuori dalla scansione oraria della classe.
     */
    public function esegui(Lezione $lezione, int $slotDestinazioneId, int $utenteId, bool $provvisorio = false, ?int $aulaPreferita = null): array
    {
        $orarioId = $lezione->orario_id;

        // Rilasciata dove già si trova: non è una modifica, né un errore da registrare.
        if ($lezione->slot_id === $slotDestinazioneId) {
            return ['ok' => true, 'errori' => [], 'avvisi' => []];
        }

        $valutazione = $this->valuta($lezione, $slotDestinazioneId);

        if ($valutazione['rigidi'] || ($valutazione['conflitti'] && ! $provvisorio)) {
            return $this->persisti($orarioId, [
                'ok' => false, 'errori' => array_values(array_unique([...$valutazione['rigidi'], ...$valutazione['conflitti']])), 'avvisi' => [],
            ], $lezione->id);
        }

        $lezioneEsistente = $valutazione['esistente'];
        $slotOrigineId = $lezione->slot_id;

        $lezione->update(['slot_id' => $slotDestinazioneId]);
        $lezioneEsistente?->update(['slot_id' => $slotOrigineId]);
        $this->riassegnaAula($lezione, $aulaPreferita);
        if ($lezioneEsistente) {
            $this->riassegnaAula($lezioneEsistente);
        }

        AuditLog::query()->create([
            'user_id' => $utenteId,
            'entita' => 'Lezione',
            'entita_id' => $lezione->id,
            'azione' => $lezioneEsistente ? 'scambio' : 'spostamento',
            'dati_prima' => ['slot_id' => $slotOrigineId, 'scambiata_con' => $lezioneEsistente?->id],
            'dati_dopo' => ['slot_id' => $slotDestinazioneId, 'scambiata_con' => $lezioneEsistente?->id],
        ]);

        $this->registra($orarioId, $lezioneEsistente ? 'scambio' : 'spostamento', $lezione->id,
            ['slot_id' => $slotOrigineId, 'scambiata_con' => $lezioneEsistente?->id],
            ['slot_id' => $slotDestinazioneId, 'scambiata_con' => $lezioneEsistente?->id], $utenteId);

        return $this->persisti($orarioId, ['ok' => true, 'errori' => [], 'avvisi' => $this->comeProvvisori($valutazione['conflitti'])], $lezione->id);
    }

    /**
     * Cambia l'aula di una lezione (stessa ora): per le materie che richiedono un tipo di aula (DADA, palestra,
     * laboratorio) l'aula deve essere di quel tipo. Un'aula già piena è un conflitto, ammesso solo in modalità provvisoria.
     */
    public function cambiaAula(Lezione $lezione, int $aulaId, int $utenteId, bool $provvisorio = false): array
    {
        $lezione->load('cattedra.classe', 'cattedra.disciplina', 'slot');
        $aula = Aula::query()->find($aulaId);
        $tipo = $lezione->cattedra->disciplina->tipo_aula_richiesto;
        $dove = $this->descriviLezione($lezione);
        $errore = fn (string $m) => $this->persisti($lezione->orario_id, ['ok' => false, 'errori' => ["{$dove}: {$m}"], 'avvisi' => []], $lezione->id);

        if (! $aula) {
            return $errore('aula non trovata.');
        }
        if ($lezione->aula_id === $aula->id) {
            return ['ok' => true, 'errori' => [], 'avvisi' => []];   // già in quell'aula: nessuna modifica, niente da registrare
        }
        if ($lezione->bloccata) {
            return $errore('la lezione è bloccata, sbloccala prima di cambiarne l\'aula.');
        }
        if (! $tipo) {
            return $errore("{$lezione->cattedra->disciplina->nome} non richiede un'aula speciale: si svolge nell'aula della classe.");
        }
        if (! $lezione->cattedra->disciplina->accettaTipo($aula->tipo)) {
            return $errore("{$aula->nome} è di tipo '{$aula->tipo}' ma serve un'aula di tipo '".implode("' o '", $lezione->cattedra->disciplina->tipiAmmessi())."'.");
        }
        $conflitti = [];
        $altre = Lezione::query()->where('orario_id', $lezione->orario_id)->where('slot_id', $lezione->slot_id)->where('aula_id', $aula->id)
            ->where('id', '!=', $lezione->id)->with('cattedra.classe', 'cattedra.disciplina')->get();
        if ($altre->pluck('cattedra.classe_id')->unique()->count() >= $aula->capienza) {
            $chi = $altre->map(fn ($l) => $l->cattedra->classe->nomeCompleto().' ('.$l->cattedra->disciplina->nome.')')->implode(' e ');
            $conflitti[] = "{$aula->nome} è già usata da {$chi}.";
        }
        if ($conflitti && ! $provvisorio) {
            return $this->persisti($lezione->orario_id, ['ok' => false, 'errori' => array_map(fn ($c) => "{$dove}: {$c}", $conflitti), 'avvisi' => []], $lezione->id);
        }

        $prima = $lezione->aula_id;
        $lezione->update(['aula_id' => $aula->id]);

        AuditLog::query()->create([
            'user_id' => $utenteId, 'entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'cambio_aula',
            'dati_prima' => ['aula_id' => $prima], 'dati_dopo' => ['aula_id' => $aula->id],
        ]);
        $this->registra($lezione->orario_id, 'cambio_aula', $lezione->id, ['aula_id' => $prima], ['aula_id' => $aula->id], $utenteId);

        return $this->persisti($lezione->orario_id, ['ok' => true, 'errori' => [], 'avvisi' => $this->comeProvvisori(array_map(fn ($c) => "{$dove}: {$c}", $conflitti))], $lezione->id);
    }

    /**
     * Per ogni cella «aula × ora» dove si può trascinare la lezione: «ok», «conflitto» (aula piena o conflitto di
     * docente, solo in modalità provvisoria) o «vietato». Chiave «{aula}-{slot}». Per le materie senza tipo di aula
     * l'unica riga possibile è l'aula della classe.
     *
     * @return array<string, array{stato: string, motivi: string[]}>
     */
    public function destinazioniAule(Lezione $lezione): array
    {
        $lezione->loadMissing('cattedra.classe.aulaBase', 'cattedra.classe.slotAttivi', 'cattedra.disciplina', 'aula');
        $tipo = $lezione->cattedra->disciplina->tipo_aula_richiesto;
        $aule = $tipo ? Aula::query()->whereIn('tipo', $lezione->cattedra->disciplina->tipiAmmessi())->orderBy('nome')->get()
            : collect([SpostamentiAula::aulaEffettiva($lezione)])->filter();
        $perSlot = $this->destinazioni($lezione) + [$lezione->slot_id => ['stato' => 'ok', 'motivi' => []]];

        $occupazione = Lezione::query()->where('orario_id', $lezione->orario_id)->whereIn('aula_id', $aule->pluck('id'))
            ->where('id', '!=', $lezione->id)->with('cattedra.classe', 'cattedra.disciplina')->get()
            ->groupBy(fn (Lezione $l) => $l->aula_id.'-'.$l->slot_id);

        $esiti = [];
        foreach ($aule as $aula) {
            foreach ($lezione->cattedra->classe->slotAttivi as $slot) {
                $esito = $perSlot[$slot->id] ?? ['stato' => 'vietato', 'motivi' => []];
                // Le lezioni della stessa classe nello slot si scambierebbero di posto: non occupano l'aula.
                $altre = ($occupazione[$aula->id.'-'.$slot->id] ?? collect())->reject(fn ($l) => $l->cattedra->classe_id === $lezione->cattedra->classe_id);
                $sonoQui = $slot->id === $lezione->slot_id && $aula->id === $lezione->aula_id;
                if ($tipo && ! $sonoQui && $esito['stato'] !== 'vietato' && $altre->pluck('cattedra.classe_id')->unique()->count() >= $aula->capienza) {
                    $chi = $altre->map(fn ($l) => $l->cattedra->classe->nomeCompleto())->unique()->implode(', ');
                    $esito = ['stato' => 'conflitto', 'motivi' => [...$esito['motivi'], "{$aula->nome} è occupata da {$chi}."]];
                }
                $esiti[$aula->id.'-'.$slot->id] = $esito;
            }
        }

        return $esiti;
    }

    /**
     * Valuta (senza modificare nulla) lo spostamento di una lezione in uno slot: l'eventuale lezione da scambiare e
     * i problemi, divisi in rigidi (mai ammessi) e conflitti (ammessi solo in modalità provvisoria).
     *
     * @return array{esistente: ?Lezione, rigidi: string[], conflitti: string[]}
     */
    private function valuta(Lezione $lezione, int $slotDestinazioneId): array
    {
        $orarioId = $lezione->orario_id;
        $lezione->load('cattedra.classe.slotAttivi', 'cattedra.docente.indisponibilita', 'cattedra.disciplina', 'slot');
        $nullo = ['esistente' => null, 'rigidi' => [], 'conflitti' => []];

        if ($lezione->bloccata) {
            return ['rigidi' => ["{$this->descriviLezione($lezione)}: la lezione è bloccata, sbloccala prima di spostarla."]] + $nullo;
        }

        $classe = $lezione->cattedra->classe;
        $esistente = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotDestinazioneId)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->first();

        if ($esistente && $esistente->bloccata) {
            return ['rigidi' => ["{$this->descriviLezione($esistente)}: la lezione nello slot di destinazione è bloccata, non si può scambiare."]] + $nullo;
        }

        $esclusioni = $esistente ? [$lezione->id, $esistente->id] : [$lezione->id];
        $verifica = $this->verificaPosizionamento($orarioId, $lezione->cattedra, $slotDestinazioneId, $esclusioni);
        if ($esistente) {
            $esistente->load('cattedra');
            $altra = $this->verificaPosizionamento($orarioId, $esistente->cattedra, $lezione->slot_id, $esclusioni);
            $verifica = ['rigidi' => [...$verifica['rigidi'], ...$altra['rigidi']], 'conflitti' => [...$verifica['conflitti'], ...$altra['conflitti']]];
        }

        return ['esistente' => $esistente, 'rigidi' => array_values(array_unique($verifica['rigidi'])), 'conflitti' => array_values(array_unique($verifica['conflitti']))];
    }

    /**
     * Per ogni slot della classe: dove si può mettere la lezione. Stati: «ok» (possibile), «conflitto» (possibile solo
     * in modalità provvisoria), «vietato» (mai). Serve a colorare la griglia mentre si trascina.
     *
     * @return array<int, array{stato: string, motivi: string[]}>
     */
    public function destinazioni(Lezione $lezione): array
    {
        $lezione->loadMissing('cattedra.classe.slotAttivi');
        $esiti = [];

        foreach ($lezione->cattedra->classe->slotAttivi as $slot) {
            if ($slot->id === $lezione->slot_id) {
                continue;
            }
            $v = $this->valuta($lezione, $slot->id);
            $esiti[$slot->id] = $v['rigidi'] ? ['stato' => 'vietato', 'motivi' => $v['rigidi']]
                : ($v['conflitti'] ? ['stato' => 'conflitto', 'motivi' => $v['conflitti']] : ['stato' => 'ok', 'motivi' => []]);
        }

        return $esiti;
    }

    /** @param  string[]  $conflitti */
    private function comeProvvisori(array $conflitti): array
    {
        return array_map(fn (string $c) => 'Conflitto provvisorio, da risolvere: '.$c, $conflitti);
    }

    /**
     * Cambia docente e/o disciplina di una singola lezione, assegnandole
     * un'altra cattedra già censita per la stessa classe (non crea cattedre
     * al volo). Valida H2/H6/H3/H7 sullo slot attuale della lezione con la
     * nuova cattedra; a differenza di esegui() non tocca lo slot.
     *
     * L'operazione può sbilanciare il monte ore delle due cattedre coinvolte
     * (non è più un semplice scambio di posizione): non blocchiamo per
     * questo, ma lo segnaliamo come avviso non bloccante.
     */
    public function cambiaCattedra(Lezione $lezione, int $nuovaCattedraId, int $utenteId, bool $provvisorio = false): array
    {
        $orarioId = $lezione->orario_id;

        if ($lezione->bloccata) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: la lezione è bloccata, sbloccala prima di modificarla."], 'avvisi' => []], $lezione->id);
        }

        $lezione->load('cattedra.classe');
        $vecchiaCattedra = $lezione->cattedra;

        if ($nuovaCattedraId === $vecchiaCattedra->id) {
            return ['ok' => true, 'errori' => [], 'avvisi' => []];   // stessa cattedra: nessuna modifica, niente da registrare
        }

        $nuovaCattedra = Cattedra::query()->with('classe', 'docente', 'disciplina')->find($nuovaCattedraId);
        if (! $nuovaCattedra) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ['Cattedra non trovata.'], 'avvisi' => []], $lezione->id);
        }
        if ($nuovaCattedra->classe_id !== $vecchiaCattedra->classe_id) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: si può assegnare solo una cattedra della stessa classe ({$vecchiaCattedra->classe->nomeCompleto()})."], 'avvisi' => []], $lezione->id);
        }

        $verifica = $this->verificaPosizionamento($orarioId, $nuovaCattedra, $lezione->slot_id, [$lezione->id]);
        if ($verifica['rigidi'] || ($verifica['conflitti'] && ! $provvisorio)) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => array_values(array_unique([...$verifica['rigidi'], ...$verifica['conflitti']])), 'avvisi' => []], $lezione->id);
        }

        $avvisi = [
            ...$this->comeProvvisori(array_values(array_unique($verifica['conflitti']))),
            ...$this->avvisiSbilanciamentoOre($orarioId, $vecchiaCattedra, $nuovaCattedra, $lezione->loadMissing('slot')),
        ];

        $lezione->update([
            'cattedra_id' => $nuovaCattedra->id,
            'aula_id' => $this->risolviAula($nuovaCattedra, $orarioId, $lezione->slot_id, [$lezione->id], $lezione->aula_id),
        ]);

        AuditLog::query()->create([
            'user_id' => $utenteId,
            'entita' => 'Lezione',
            'entita_id' => $lezione->id,
            'azione' => 'cambio_cattedra',
            'dati_prima' => ['cattedra_id' => $vecchiaCattedra->id],
            'dati_dopo' => ['cattedra_id' => $nuovaCattedra->id],
        ]);

        $this->registra($orarioId, 'cambio_cattedra', $lezione->id, ['cattedra_id' => $vecchiaCattedra->id], ['cattedra_id' => $nuovaCattedra->id], $utenteId);

        return $this->persisti($orarioId, ['ok' => true, 'errori' => [], 'avvisi' => $avvisi], $lezione->id);
    }

    /** Blocca o sblocca una lezione (annullabile come le altre modifiche). */
    public function blocca(Lezione $lezione, int $utenteId): bool
    {
        $prima = $lezione->bloccata;
        $lezione->update(['bloccata' => ! $prima]);

        AuditLog::query()->create([
            'user_id' => $utenteId, 'entita' => 'Lezione', 'entita_id' => $lezione->id, 'azione' => 'blocco',
            'dati_prima' => ['bloccata' => $prima], 'dati_dopo' => ['bloccata' => ! $prima],
        ]);
        $this->registra($lezione->orario_id, 'blocco', $lezione->id, ['bloccata' => $prima], ['bloccata' => ! $prima], $utenteId);

        return $lezione->bloccata;
    }

    public function puoAnnullare(Orario $orario): bool
    {
        return ModificaOrario::query()->where('orario_id', $orario->id)->where('annullata', false)->exists();
    }

    public function puoRipetere(Orario $orario): bool
    {
        return ModificaOrario::query()->where('orario_id', $orario->id)->where('annullata', true)->exists();
    }

    /**
     * Annulla l'ultima modifica ancora attiva (su più livelli). Non cancella nulla dal registro delle modifiche:
     * la voce passa sullo stack "ripeti" e l'annullamento stesso viene registrato.
     *
     * @return array{ok: bool, messaggio: string}
     */
    public function annulla(Orario $orario, int $utenteId): array
    {
        $voce = ModificaOrario::query()->where('orario_id', $orario->id)->where('annullata', false)->latest('id')->first();

        return $voce ? $this->applicaVoce($voce, 'prima', $utenteId) : ['ok' => false, 'messaggio' => 'Nessuna modifica da annullare.'];
    }

    /**
     * Ripete l'ultima modifica annullata.
     *
     * @return array{ok: bool, messaggio: string}
     */
    public function ripeti(Orario $orario, int $utenteId): array
    {
        $voce = ModificaOrario::query()->where('orario_id', $orario->id)->where('annullata', true)->oldest('id')->first();

        return $voce ? $this->applicaVoce($voce, 'dopo', $utenteId) : ['ok' => false, 'messaggio' => 'Nessuna modifica da ripetere.'];
    }

    /** Una nuova modifica cancella lo stack "ripeti" (come in ogni editor); l'audit log conserva tutto. */
    private function registra(int $orarioId, string $tipo, int $lezioneId, array $prima, array $dopo, int $utenteId): void
    {
        ModificaOrario::query()->where('orario_id', $orarioId)->where('annullata', true)->delete();
        ModificaOrario::query()->create([
            'orario_id' => $orarioId, 'user_id' => $utenteId, 'tipo' => $tipo, 'lezione_id' => $lezioneId,
            'dati_prima' => $prima, 'dati_dopo' => $dopo,
        ]);
    }

    /** Porta la lezione allo stato "prima" (annulla) o "dopo" (ripeti) della voce. */
    private function applicaVoce(ModificaOrario $voce, string $lato, int $utenteId): array
    {
        $stato = $voce->{"dati_$lato"};
        $altro = $voce->{'dati_'.($lato === 'prima' ? 'dopo' : 'prima')};
        $lezione = Lezione::query()->find($voce->lezione_id);
        if (! $lezione) {
            $voce->delete();

            return ['ok' => false, 'messaggio' => 'Impossibile: la lezione interessata non esiste più.'];
        }

        match ($voce->tipo) {
            'spostamento', 'scambio' => $this->riportaSlot($lezione, $stato, $altro),
            'cambio_cattedra' => $lezione->update([
                'cattedra_id' => $stato['cattedra_id'],
                'aula_id' => $this->risolviAula(Cattedra::query()->findOrFail($stato['cattedra_id']), $lezione->orario_id, $lezione->slot_id, [$lezione->id], $lezione->aula_id),
            ]),
            'blocco' => $lezione->update(['bloccata' => $stato['bloccata']]),
            'cambio_aula' => $lezione->update(['aula_id' => $stato['aula_id']]),
        };

        $voce->update(['annullata' => $lato === 'prima']);
        AuditLog::query()->create([
            'user_id' => $utenteId, 'entita' => 'Lezione', 'entita_id' => $lezione->id,
            'azione' => $lato === 'prima' ? 'annullamento' : 'ripristino',
            'dati_prima' => ['tipo' => $voce->tipo], 'dati_dopo' => $stato,
        ]);

        return ['ok' => true, 'messaggio' => ($lato === 'prima' ? 'Annullato: ' : 'Ripetuto: ').$voce->descrizione().'.'];
    }

    private function riportaSlot(Lezione $lezione, array $stato, array $altro): void
    {
        $lezione->update(['slot_id' => $stato['slot_id']]);
        // In uno scambio l'altra lezione va dove si trovava la prima nell'altro stato.
        if (! empty($stato['scambiata_con'])) {
            Lezione::query()->where('id', $stato['scambiata_con'])->update(['slot_id' => $altro['slot_id']]);
        }
        // L'aula dipende dall'ora: va ricalcolata anche annullando o ripetendo.
        $this->riassegnaAula($lezione);
        if (! empty($stato['scambiata_con']) && ($altra = Lezione::query()->find($stato['scambiata_con']))) {
            $this->riassegnaAula($altra);
        }
    }

    private function persisti(int $orarioId, array $risultato, int $lezioneId): array
    {
        foreach ($risultato['errori'] as $messaggio) {
            AvvisoOrario::query()->create(['orario_id' => $orarioId, 'lezione_id' => $lezioneId, 'tipo' => 'errore', 'messaggio' => $messaggio]);
        }
        foreach ($risultato['avvisi'] as $messaggio) {
            AvvisoOrario::query()->create(['orario_id' => $orarioId, 'lezione_id' => $lezioneId, 'tipo' => 'avviso', 'messaggio' => $messaggio]);
        }

        return $risultato;
    }

    /**
     * Cambiare cattedra sposta un'ora da una cattedra all'altra: se una delle due non torna più sul monte ore previsto
     * si avvisa, dicendo chiaramente quale è quella lasciata e quale quella scelta.
     *
     * @return string[]
     */
    private function avvisiSbilanciamentoOre(int $orarioId, Cattedra $vecchia, Cattedra $nuova, Lezione $lezione): array
    {
        $avvisi = [];
        $vecchia->loadMissing('classe', 'disciplina', 'docente');
        $nuova->loadMissing('classe', 'disciplina', 'docente');
        $nome = fn (Cattedra $c) => "{$c->disciplina->nome} ({$c->docente->nomeCompleto()})";
        $prefisso = "{$vecchia->classe->nomeCompleto()}, {$lezione->slot->descrizione()}: ";

        $oreVecchia = Lezione::query()->where('orario_id', $orarioId)->where('cattedra_id', $vecchia->id)->count() - 1;
        if ($oreVecchia !== $vecchia->ore) {
            $avvisi[] = $prefisso."cattedra lasciata, {$nome($vecchia)} avrà {$oreVecchia} ore invece delle {$vecchia->ore} previste.";
        }

        $oreNuova = Lezione::query()->where('orario_id', $orarioId)->where('cattedra_id', $nuova->id)->where('id', '!=', $lezione->id)->count() + 1;
        if ($oreNuova !== $nuova->ore) {
            $avvisi[] = $prefisso."cattedra scelta, {$nome($nuova)} avrà {$oreNuova} ore invece delle {$nuova->ore} previste.";
        }

        return $avvisi;
    }

    /**
     * Aula per la lezione nello slot: nessuna se la disciplina non richiede un tipo di aula (la classe resta nella sua);
     * altrimenti un'aula di quel tipo con posto libero (capienza = lezioni contemporanee), preferendo $preferita se
     * è ancora disponibile, così le lezioni di una disciplina restano nella stessa aula quando possibile.
     */
    private function risolviAula(Cattedra $cattedra, int $orarioId, int $slotId, array $lezioniEscluse, ?int $preferita = null): ?int
    {
        $cattedra->loadMissing('disciplina');
        $tipo = $cattedra->disciplina->tipo_aula_richiesto;

        if (! $tipo) {
            return null;
        }

        $occupazione = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotId)
            ->whereNotIn('id', $lezioniEscluse)
            ->whereNotNull('aula_id')
            ->with('cattedra')
            ->get()
            ->groupBy('aula_id')
            ->map(fn ($gruppo) => $gruppo->pluck('cattedra.classe_id')->unique()->count());

        $libere = Aula::query()->whereIn('tipo', $cattedra->disciplina->tipiAmmessi())->orderBy('id')->get()
            ->filter(fn (Aula $a) => ($occupazione[$a->id] ?? 0) < $a->capienza);

        return ($preferita && $libere->contains('id', $preferita)) ? $preferita : $libere->first()?->id;
    }

    /** Dopo uno spostamento ricalcola l'aula della lezione (in DADA l'aula dipende da ora e disponibilità). */
    private function riassegnaAula(Lezione $lezione, ?int $aulaPreferita = null): void
    {
        $lezione->refresh()->loadMissing('cattedra.disciplina');
        $lezione->update(['aula_id' => $this->risolviAula($lezione->cattedra, $lezione->orario_id, $lezione->slot_id, [$lezione->id], $aulaPreferita ?? $lezione->aula_id)]);
    }

    /** @return string[] messaggi di violazione (vuoto = posizionamento valido) */
    /** «Italiano – Rossi Anna, 1ª A, lunedì, 2ª ora (08:50–09:40)»: quale lezione è coinvolta. */
    private function descriviLezione(Lezione $lezione): string
    {
        $lezione->loadMissing('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'slot');

        return "{$lezione->cattedra->disciplina->nome} ({$lezione->cattedra->docente->nomeCompleto()}), {$lezione->cattedra->classe->nomeCompleto()}, {$lezione->slot->descrizione()}";
    }

    /**
     * Ogni messaggio dice classe, giorno e ora del conflitto e, dove serve, l'altra lezione coinvolta.
     *
     * @return array{rigidi: string[], conflitti: string[]} rigidi = slot fuori scansione; conflitti = docente/aula
     */
    private function verificaPosizionamento(int $orarioId, Cattedra $cattedra, int $slotId, array $lezioniEscluse): array
    {
        $cattedra->loadMissing('classe.slotAttivi', 'docente.indisponibilita', 'disciplina');

        $rigidi = [];
        $conflitti = [];
        $classe = $cattedra->classe;
        $docente = $cattedra->docente;
        $disciplina = $cattedra->disciplina;
        $dove = Slot::query()->find($slotId)?->descrizione() ?? 'slot sconosciuto';
        $prefisso = "{$classe->nomeCompleto()}, {$dove}: ";

        if (! $classe->slotAttivi->pluck('id')->contains($slotId)) {
            $rigidi[] = $prefisso."{$disciplina->nome} non si può mettere qui, perché l'ora non fa parte della scansione oraria della classe.";
        }

        if ($docente->indisponibilita->pluck('id')->contains($slotId)) {
            $conflitti[] = $prefisso."il docente {$docente->nomeCompleto()} ({$disciplina->nome}) non è disponibile in quell'ora.";
        }

        $altra = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotId)
            ->whereNotIn('id', $lezioniEscluse)
            ->whereHas('cattedra', fn ($q) => $q->where('docente_id', $docente->id))
            ->with('cattedra.classe', 'cattedra.disciplina')
            ->first();
        if ($altra) {
            $conflitti[] = $prefisso."il docente {$docente->nomeCompleto()} ({$disciplina->nome}) è già impegnato in {$altra->cattedra->classe->nomeCompleto()} con {$altra->cattedra->disciplina->nome}.";
        }

        if ($disciplina->tipo_aula_richiesto) {
            $tipi = $disciplina->tipiAmmessi();
            $capienzaTotale = Aula::query()->whereIn('tipo', $tipi)->sum('capienza');
            $occupanti = Lezione::query()
                ->where('orario_id', $orarioId)
                ->where('slot_id', $slotId)
                ->whereNotIn('id', $lezioniEscluse)
                ->whereHas('cattedra', fn ($q) => $q->whereIn('disciplina_id', \App\Models\Disciplina::idCheAmmettono($tipi)))
                ->with('cattedra.classe')
                ->get();
            if ($occupanti->count() >= $capienzaTotale) {
                $chi = $occupanti->map(fn ($l) => $l->cattedra->classe->nomeCompleto())->unique()->implode(', ');
                $conflitti[] = $prefisso."nessuna aula di tipo '{$disciplina->tipo_aula_richiesto}' libera per {$disciplina->nome}".($chi ? " (occupata da {$chi})" : '').'.';
            }
        }

        return ['rigidi' => $rigidi, 'conflitti' => $conflitti];
    }
}
