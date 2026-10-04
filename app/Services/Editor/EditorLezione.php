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
    public function esegui(Lezione $lezione, int $slotDestinazioneId, int $utenteId): array
    {
        $orarioId = $lezione->orario_id;

        if ($lezione->bloccata) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: la lezione è bloccata, sbloccala prima di spostarla."], 'avvisi' => []]);
        }

        $lezione->load('cattedra.classe.slotAttivi', 'cattedra.docente.indisponibilita', 'cattedra.disciplina');
        $classe = $lezione->cattedra->classe;
        $lezioneEsistente = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotDestinazioneId)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->first();

        if ($lezioneEsistente && $lezioneEsistente->bloccata) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezioneEsistente)}: la lezione nello slot di destinazione è bloccata, non si può scambiare."], 'avvisi' => []]);
        }

        $slotOrigineId = $lezione->slot_id;
        $esclusioni = $lezioneEsistente ? [$lezione->id, $lezioneEsistente->id] : [$lezione->id];

        $errori = $this->verificaPosizionamento($orarioId, $lezione->cattedra, $slotDestinazioneId, $esclusioni);
        if ($lezioneEsistente) {
            $lezioneEsistente->load('cattedra');
            $errori = array_merge($errori, $this->verificaPosizionamento($orarioId, $lezioneEsistente->cattedra, $slotOrigineId, $esclusioni));
        }

        if ($errori) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => array_unique($errori), 'avvisi' => []]);
        }

        $lezione->update(['slot_id' => $slotDestinazioneId]);
        $lezioneEsistente?->update(['slot_id' => $slotOrigineId]);

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

        return $this->persisti($orarioId, ['ok' => true, 'errori' => [], 'avvisi' => []]);
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
    public function cambiaCattedra(Lezione $lezione, int $nuovaCattedraId, int $utenteId): array
    {
        $orarioId = $lezione->orario_id;

        if ($lezione->bloccata) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: la lezione è bloccata, sbloccala prima di modificarla."], 'avvisi' => []]);
        }

        $lezione->load('cattedra.classe');
        $vecchiaCattedra = $lezione->cattedra;

        if ($nuovaCattedraId === $vecchiaCattedra->id) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: nessuna modifica, è già la cattedra assegnata."], 'avvisi' => []]);
        }

        $nuovaCattedra = Cattedra::query()->with('classe', 'docente', 'disciplina')->find($nuovaCattedraId);
        if (! $nuovaCattedra) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ['Cattedra non trovata.'], 'avvisi' => []]);
        }
        if ($nuovaCattedra->classe_id !== $vecchiaCattedra->classe_id) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => ["{$this->descriviLezione($lezione)}: si può assegnare solo una cattedra della stessa classe ({$vecchiaCattedra->classe->nomeCompleto()})."], 'avvisi' => []]);
        }

        $errori = $this->verificaPosizionamento($orarioId, $nuovaCattedra, $lezione->slot_id, [$lezione->id]);
        if ($errori) {
            return $this->persisti($orarioId, ['ok' => false, 'errori' => array_unique($errori), 'avvisi' => []]);
        }

        $avvisi = $this->avvisiSbilanciamentoOre($orarioId, $vecchiaCattedra, $nuovaCattedra, $lezione->loadMissing('slot'));

        $lezione->update([
            'cattedra_id' => $nuovaCattedra->id,
            'aula_id' => $this->risolviAula($nuovaCattedra, $orarioId, $lezione->slot_id, [$lezione->id]),
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

        return $this->persisti($orarioId, ['ok' => true, 'errori' => [], 'avvisi' => $avvisi]);
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
                'aula_id' => $this->risolviAula(Cattedra::query()->findOrFail($stato['cattedra_id']), $lezione->orario_id, $lezione->slot_id, [$lezione->id]),
            ]),
            'blocco' => $lezione->update(['bloccata' => $stato['bloccata']]),
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
    }

    private function persisti(int $orarioId, array $risultato): array
    {
        foreach ($risultato['errori'] as $messaggio) {
            AvvisoOrario::query()->create(['orario_id' => $orarioId, 'tipo' => 'errore', 'messaggio' => $messaggio]);
        }
        foreach ($risultato['avvisi'] as $messaggio) {
            AvvisoOrario::query()->create(['orario_id' => $orarioId, 'tipo' => 'avviso', 'messaggio' => $messaggio]);
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

    /** Aula coerente con il tipo richiesto dalla disciplina della cattedra, se serve. */
    private function risolviAula(Cattedra $cattedra, int $orarioId, int $slotId, array $lezioniEscluse): ?int
    {
        $cattedra->loadMissing('classe', 'disciplina');

        if (! $cattedra->disciplina->tipo_aula_richiesto) {
            return $cattedra->classe->aula_base_id;
        }

        $occupate = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotId)
            ->whereNotIn('id', $lezioniEscluse)
            ->pluck('aula_id')
            ->filter()
            ->all();

        return Aula::query()
            ->where('tipo', $cattedra->disciplina->tipo_aula_richiesto)
            ->whereNotIn('id', $occupate)
            ->value('id');
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
     * @return string[]
     */
    private function verificaPosizionamento(int $orarioId, Cattedra $cattedra, int $slotId, array $lezioniEscluse): array
    {
        $cattedra->loadMissing('classe.slotAttivi', 'docente.indisponibilita', 'disciplina');

        $errori = [];
        $classe = $cattedra->classe;
        $docente = $cattedra->docente;
        $disciplina = $cattedra->disciplina;
        $dove = Slot::query()->find($slotId)?->descrizione() ?? 'slot sconosciuto';
        $prefisso = "{$classe->nomeCompleto()}, {$dove}: ";

        if (! $classe->slotAttivi->pluck('id')->contains($slotId)) {
            $errori[] = $prefisso."{$disciplina->nome} non si può mettere qui, perché l'ora non fa parte della scansione oraria della classe.";
        }

        if ($docente->indisponibilita->pluck('id')->contains($slotId)) {
            $errori[] = $prefisso."il docente {$docente->nomeCompleto()} ({$disciplina->nome}) non è disponibile in quell'ora.";
        }

        $altra = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotId)
            ->whereNotIn('id', $lezioniEscluse)
            ->whereHas('cattedra', fn ($q) => $q->where('docente_id', $docente->id))
            ->with('cattedra.classe', 'cattedra.disciplina')
            ->first();
        if ($altra) {
            $errori[] = $prefisso."il docente {$docente->nomeCompleto()} ({$disciplina->nome}) è già impegnato in {$altra->cattedra->classe->nomeCompleto()} con {$altra->cattedra->disciplina->nome}.";
        }

        if ($disciplina->tipo_aula_richiesto) {
            $capienzaTotale = Aula::query()->where('tipo', $disciplina->tipo_aula_richiesto)->sum('capienza');
            $occupanti = Lezione::query()
                ->where('orario_id', $orarioId)
                ->where('slot_id', $slotId)
                ->whereNotIn('id', $lezioniEscluse)
                ->whereHas('cattedra.disciplina', fn ($q) => $q->where('tipo_aula_richiesto', $disciplina->tipo_aula_richiesto))
                ->with('cattedra.classe')
                ->get();
            if ($occupanti->count() >= $capienzaTotale) {
                $chi = $occupanti->map(fn ($l) => $l->cattedra->classe->nomeCompleto())->unique()->implode(', ');
                $errori[] = $prefisso."nessuna aula di tipo '{$disciplina->tipo_aula_richiesto}' libera per {$disciplina->nome}".($chi ? " (occupata da {$chi})" : '').'.';
            }
        }

        return $errori;
    }
}
