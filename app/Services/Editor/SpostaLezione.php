<?php

namespace App\Services\Editor;

use App\Models\AuditLog;
use App\Models\Aula;
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
class SpostaLezione
{
    public function esegui(Lezione $lezione, int $slotDestinazioneId, int $utenteId): array
    {
        if ($lezione->bloccata) {
            return ['ok' => false, 'errori' => ['La lezione è bloccata: sbloccala prima di spostarla.']];
        }

        $lezione->load('cattedra.classe.slotAttivi', 'cattedra.docente.indisponibilita', 'cattedra.disciplina');
        $classe = $lezione->cattedra->classe;
        $lezioneEsistente = Lezione::query()
            ->where('orario_id', $lezione->orario_id)
            ->where('slot_id', $slotDestinazioneId)
            ->whereHas('cattedra', fn ($q) => $q->where('classe_id', $classe->id))
            ->first();

        if ($lezioneEsistente && $lezioneEsistente->bloccata) {
            return ['ok' => false, 'errori' => ['La lezione nello slot di destinazione è bloccata.']];
        }

        $slotOrigineId = $lezione->slot_id;
        $esclusioni = $lezioneEsistente ? [$lezione->id, $lezioneEsistente->id] : [$lezione->id];

        $errori = $this->verificaPosizionamento($lezione, $slotDestinazioneId, $esclusioni);
        if ($lezioneEsistente) {
            $errori = array_merge($errori, $this->verificaPosizionamento($lezioneEsistente, $slotOrigineId, $esclusioni));
        }

        if ($errori) {
            return ['ok' => false, 'errori' => array_unique($errori)];
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

        return ['ok' => true, 'errori' => []];
    }

    /** Annulla l'ultima modifica (spostamento/scambio) registrata per questo orario. */
    public function annullaUltima(Orario $orario): bool
    {
        $lezioneIds = $orario->lezioni()->pluck('id');

        $log = AuditLog::query()
            ->where('entita', 'Lezione')
            ->whereIn('entita_id', $lezioneIds)
            ->whereIn('azione', ['spostamento', 'scambio'])
            ->latest('id')
            ->first();

        if (! $log) {
            return false;
        }

        Lezione::query()->where('id', $log->entita_id)->update(['slot_id' => $log->dati_prima['slot_id']]);

        $scambiataConId = $log->dati_prima['scambiata_con'] ?? null;
        if ($scambiataConId) {
            Lezione::query()->where('id', $scambiataConId)->update(['slot_id' => $log->dati_dopo['slot_id']]);
        }

        $log->delete();

        return true;
    }

    /** @return string[] messaggi di violazione (vuoto = posizionamento valido) */
    private function verificaPosizionamento(Lezione $lezione, int $slotId, array $lezioniEscluse): array
    {
        $errori = [];
        $cattedra = $lezione->cattedra;
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
            ->where('orario_id', $lezione->orario_id)
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
                ->where('orario_id', $lezione->orario_id)
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
