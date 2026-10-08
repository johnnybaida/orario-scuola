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
        return Slot::query()->whereNotNull('ricreazione_minuti')->orderBy('ordine')->orderBy('giorno')->get()->groupBy('ordine')
            ->map(function (Collection $slot) {
                $s = $slot->first();
                $da = substr($s->fine, 0, 5);

                return ['ordine' => $s->ordine, 'nome' => $s->nomePausa(), 'da' => $da, 'a' => $s->fineRicreazione(), 'minuti' => (int) $s->ricreazione_minuti,
                    'etichetta' => "{$s->nomePausa()} {$da}–{$s->fineRicreazione()} (dopo la {$s->ordine}ª ora)"];
            });
    }

    /** @return list<string> es. «Lun · Mensa 13:00–13:40», ordinate per giorno; le assistenze su pause non più esistenti sono omesse */
    public function elenco(Docente $docente): array
    {
        $pause = $this->pause();

        return $docente->assistenzePausa->filter(fn ($a) => $pause->has($a->ordine))
            ->map(fn ($a) => ucfirst(mb_strtolower(Slot::GIORNI_BREVI[$a->giorno] ?? (string) $a->giorno)).' · '.$pause[$a->ordine]['nome'].' '.$pause[$a->ordine]['da'].'–'.$pause[$a->ordine]['a'])->values()->all();
    }

    /** Minuti settimanali di assistenza. */
    public function minuti(Docente $docente): int
    {
        $pause = $this->pause();

        return (int) $docente->assistenzePausa->sum(fn ($a) => $pause[$a->ordine]['minuti'] ?? 0);
    }
}
