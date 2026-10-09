<?php

namespace App\Services;

use App\Models\Docente;
use App\Models\Slot;
use Illuminate\Support\Collection;

/** Pause della scansione oraria (ricreazione, mensa, ...) e assistenza dei docenti in quelle pause. */
class AssistenzaPause
{
    /** @return Collection<int, array{ordine: int, nome: string, da: string, a: string, minuti: int, etichetta: string}> per ordine dell'ora che precede la pausa */
    public function pause(): Collection
    {
        $dopo = Slot::query()->whereNotNull('ricreazione_minuti')->orderBy('ordine')->orderBy('giorno')->get()->groupBy('ordine')
            ->map(function (Collection $slot) {
                $s = $slot->first();
                $da = substr($s->fine, 0, 5);

                return ['ordine' => $s->ordine, 'nome' => $s->nomePausa(), 'da' => $da, 'a' => $s->fineRicreazione(), 'minuti' => (int) $s->ricreazione_minuti,
                    'conteggio' => self::conteggio($s->ricreazione_conteggio, $s->ricreazione_minuti), 'aula' => $s->ricreazioneAula?->nome,
                    'etichetta' => "{$s->nomePausa()} {$da}–{$s->fineRicreazione()} (dopo la {$s->ordine}ª ora)"];
            });

        // La pausa prima della prima ora ha chiave 0 («ordine» dell'ora che la precede: nessuna).
        $primo = Slot::query()->orderBy('ordine')->orderBy('giorno')->first();
        if ($primo?->pausa_prima_minuti) {
            $a = substr($primo->inizio, 0, 5);
            $dopo->prepend(['ordine' => 0, 'nome' => $primo->nomePausaPrima(), 'da' => $primo->inizioPausaPrima(), 'a' => $a, 'minuti' => (int) $primo->pausa_prima_minuti,
                'conteggio' => self::conteggio($primo->pausa_prima_conteggio, $primo->pausa_prima_minuti), 'aula' => $primo->pausaPrimaAula?->nome,
                'etichetta' => "{$primo->nomePausaPrima()} {$primo->inizioPausaPrima()}–{$a} (prima della {$primo->ordine}ª ora)"], 0);
        }

        return $dopo;
    }

    /** @return list<string> es. «Lun · Mensa 13:00–13:40», ordinate per giorno; le assistenze su pause non più esistenti sono omesse */
    public function elenco(Docente $docente): array
    {
        $pause = $this->pause();

        return $docente->assistenzePausa->filter(fn ($a) => $pause->has($a->ordine))
            ->map(fn ($a) => ucfirst(mb_strtolower(Slot::GIORNI_BREVI[$a->giorno] ?? (string) $a->giorno)).' · '.$pause[$a->ordine]['nome'].' '.$pause[$a->ordine]['da'].'–'.$pause[$a->ordine]['a'].($pause[$a->ordine]['aula'] ? ' ('.$pause[$a->ordine]['aula'].')' : ''))->values()->all();
    }

    /** Minuti con cui la pausa conta nel monte ore: quelli scelti in Scansione oraria, altrimenti la durata arrotondata per eccesso al quarto d'ora. */
    public static function conteggio(?int $scelto, ?int $durata): int
    {
        return $scelto ?: (int) (ceil(((int) $durata) / 15) * 15);
    }

    /** Ore di assistenza (60 minuti conteggiati = 1 ora) che si sommano a quelle di cattedra e sostegno nel monte ore del docente. */
    public function ore(Docente $docente): float
    {
        $pause = $this->pause();

        return round($docente->assistenzePausa->sum(fn ($a) => $pause[$a->ordine]['conteggio'] ?? 0) / 60, 2);
    }

    /** «21,5» invece di «21.50»: per i totali a video. */
    public static function formatta(float|int $ore): string
    {
        return rtrim(rtrim(number_format($ore, 2, ',', ''), '0'), ',');
    }

    /** Minuti settimanali di assistenza. */
    public function minuti(Docente $docente): int
    {
        $pause = $this->pause();

        return (int) $docente->assistenzePausa->sum(fn ($a) => $pause[$a->ordine]['minuti'] ?? 0);
    }
}
