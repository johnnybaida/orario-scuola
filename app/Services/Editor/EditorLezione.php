<?php

namespace App\Services\Editor;

use App\Models\AuditLog;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Lezione;
use App\Models\Orario;

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
        if ($lezione->bloccata) {
            return ['ok' => false, 'errori' => ['La lezione è bloccata: sbloccala prima di spostarla.'], 'avvisi' => []];
        }

        $lezione->load('cattedra.classe.slotAttivi', 'cattedra.docente.indisponibilita', 'cattedra.disciplina');
        $classe = $lezione->cattedra->classe;
        $lezioneEsistente = Lezione::query()
            ->where('orario_id', $lezione->orario_id)
            ->where('slot_id', $slotDestinazioneId)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->first();

        if ($lezioneEsistente && $lezioneEsistente->bloccata) {
            return ['ok' => false, 'errori' => ['La lezione nello slot di destinazione è bloccata.'], 'avvisi' => []];
        }

        $slotOrigineId = $lezione->slot_id;
        $esclusioni = $lezioneEsistente ? [$lezione->id, $lezioneEsistente->id] : [$lezione->id];

        $errori = $this->verificaPosizionamento($lezione->orario_id, $lezione->cattedra, $slotDestinazioneId, $esclusioni);
        if ($lezioneEsistente) {
            $lezioneEsistente->load('cattedra');
            $errori = array_merge($errori, $this->verificaPosizionamento($lezione->orario_id, $lezioneEsistente->cattedra, $slotOrigineId, $esclusioni));
        }

        if ($errori) {
            return ['ok' => false, 'errori' => array_unique($errori), 'avvisi' => []];
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

        return ['ok' => true, 'errori' => [], 'avvisi' => []];
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
        if ($lezione->bloccata) {
            return ['ok' => false, 'errori' => ['La lezione è bloccata: sbloccala prima di modificarla.'], 'avvisi' => []];
        }

        $lezione->load('cattedra.classe');
        $vecchiaCattedra = $lezione->cattedra;

        if ($nuovaCattedraId === $vecchiaCattedra->id) {
            return ['ok' => false, 'errori' => ['Nessuna modifica: è già la cattedra assegnata.'], 'avvisi' => []];
        }

        $nuovaCattedra = Cattedra::query()->with('classe', 'docente', 'disciplina')->find($nuovaCattedraId);
        if (! $nuovaCattedra) {
            return ['ok' => false, 'errori' => ['Cattedra non trovata.'], 'avvisi' => []];
        }
        if ($nuovaCattedra->classe_id !== $vecchiaCattedra->classe_id) {
            return ['ok' => false, 'errori' => ['Puoi assegnare solo una cattedra della stessa classe.'], 'avvisi' => []];
        }

        $errori = $this->verificaPosizionamento($lezione->orario_id, $nuovaCattedra, $lezione->slot_id, [$lezione->id]);
        if ($errori) {
            return ['ok' => false, 'errori' => array_unique($errori), 'avvisi' => []];
        }

        $avvisi = $this->avvisiSbilanciamentoOre($lezione->orario_id, $vecchiaCattedra, $nuovaCattedra, $lezione->id);

        $lezione->update([
            'cattedra_id' => $nuovaCattedra->id,
            'aula_id' => $this->risolviAula($nuovaCattedra, $lezione->orario_id, $lezione->slot_id, [$lezione->id]),
        ]);

        AuditLog::query()->create([
            'user_id' => $utenteId,
            'entita' => 'Lezione',
            'entita_id' => $lezione->id,
            'azione' => 'cambio_cattedra',
            'dati_prima' => ['cattedra_id' => $vecchiaCattedra->id],
            'dati_dopo' => ['cattedra_id' => $nuovaCattedra->id],
        ]);

        return ['ok' => true, 'errori' => [], 'avvisi' => $avvisi];
    }

    /** Annulla l'ultima modifica (spostamento/scambio/cambio cattedra) registrata per questo orario. */
    public function annullaUltima(Orario $orario): bool
    {
        $lezioneIds = $orario->lezioni()->pluck('id');

        $log = AuditLog::query()
            ->where('entita', 'Lezione')
            ->whereIn('entita_id', $lezioneIds)
            ->whereIn('azione', ['spostamento', 'scambio', 'cambio_cattedra'])
            ->latest('id')
            ->first();

        if (! $log) {
            return false;
        }

        if ($log->azione === 'cambio_cattedra') {
            $lezione = Lezione::find($log->entita_id);
            $lezione->update([
                'cattedra_id' => $log->dati_prima['cattedra_id'],
                'aula_id' => $this->risolviAula(
                    Cattedra::find($log->dati_prima['cattedra_id']),
                    $lezione->orario_id,
                    $lezione->slot_id,
                    [$lezione->id],
                ),
            ]);
            $log->delete();

            return true;
        }

        Lezione::query()->where('id', $log->entita_id)->update(['slot_id' => $log->dati_prima['slot_id']]);

        $scambiataConId = $log->dati_prima['scambiata_con'] ?? null;
        if ($scambiataConId) {
            Lezione::query()->where('id', $scambiataConId)->update(['slot_id' => $log->dati_dopo['slot_id']]);
        }

        $log->delete();

        return true;
    }

    /** @return string[] */
    private function avvisiSbilanciamentoOre(int $orarioId, Cattedra $vecchia, Cattedra $nuova, int $lezioneId): array
    {
        $avvisi = [];

        $oreAttualiVecchia = Lezione::query()->where('orario_id', $orarioId)->where('cattedra_id', $vecchia->id)->count();
        if ($oreAttualiVecchia - 1 !== $vecchia->ore) {
            $avvisi[] = "{$vecchia->disciplina->nome} ({$vecchia->docente->nomeCompleto()}) avrà ".($oreAttualiVecchia - 1)." ore invece delle {$vecchia->ore} previste.";
        }

        $oreAttualiNuova = Lezione::query()->where('orario_id', $orarioId)->where('cattedra_id', $nuova->id)->where('id', '!=', $lezioneId)->count();
        if ($oreAttualiNuova + 1 !== $nuova->ore) {
            $avvisi[] = "{$nuova->disciplina->nome} ({$nuova->docente->nomeCompleto()}) avrà ".($oreAttualiNuova + 1)." ore invece delle {$nuova->ore} previste.";
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
    private function verificaPosizionamento(int $orarioId, Cattedra $cattedra, int $slotId, array $lezioniEscluse): array
    {
        $cattedra->loadMissing('classe.slotAttivi', 'docente.indisponibilita', 'disciplina');

        $errori = [];
        $classe = $cattedra->classe;
        $docente = $cattedra->docente;
        $disciplina = $cattedra->disciplina;

        if (! $classe->slotAttivi->pluck('id')->contains($slotId)) {
            $errori[] = "Lo slot scelto non fa parte della scansione oraria di {$classe->nomeCompleto()}.";
        }

        if ($docente->indisponibilita->pluck('id')->contains($slotId)) {
            $errori[] = "Il docente {$docente->nomeCompleto()} non è disponibile in quello slot.";
        }

        $docenteOccupato = Lezione::query()
            ->where('orario_id', $orarioId)
            ->where('slot_id', $slotId)
            ->whereNotIn('id', $lezioniEscluse)
            ->whereHas('cattedra', fn ($q) => $q->where('docente_id', $docente->id))
            ->exists();
        if ($docenteOccupato) {
            $errori[] = "Il docente {$docente->nomeCompleto()} ha già una lezione in quello slot.";
        }

        if ($disciplina->tipo_aula_richiesto) {
            $capienzaTotale = Aula::query()->where('tipo', $disciplina->tipo_aula_richiesto)->sum('capienza');
            $occupanti = Lezione::query()
                ->where('orario_id', $orarioId)
                ->where('slot_id', $slotId)
                ->whereNotIn('id', $lezioniEscluse)
                ->whereHas('cattedra.disciplina', fn ($q) => $q->where('tipo_aula_richiesto', $disciplina->tipo_aula_richiesto))
                ->count();
            if ($occupanti >= $capienzaTotale) {
                $errori[] = "Nessuna aula di tipo '{$disciplina->tipo_aula_richiesto}' libera in quello slot.";
            }
        }

        return $errori;
    }
}
