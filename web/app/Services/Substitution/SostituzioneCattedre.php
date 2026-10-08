<?php

namespace App\Services\Substitution;

use App\Models\Cattedra;
use App\Models\Sospensione;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Passa le cattedre di un docente sospeso ai supplenti indicati sulla sospensione e le riporta al titolare quando
 * rientra. Si cambia solo `cattedre.docente_id`: le lezioni degli orari seguono la cattedra, quindi il supplente
 * eredita l'orario. `cattedre.sospensione_id` ricorda da dove arrivano (il titolare è `sospensione->docente`).
 */
class SostituzioneCattedre
{
    /**
     * @param  array<int, int|string|null>  $mappa  id cattedra => id supplente (vuoto = la cattedra resta al titolare)
     * @return int cattedre passate
     *
     * @throws ValidationException
     */
    public function assegna(Sospensione $sospensione, array $mappa): int
    {
        $mappa = array_filter($mappa);
        $supplenti = $sospensione->supplenti()->pluck('docenti.id')->all();
        $cattedre = $sospensione->docente->cattedre()->whereIn('id', array_keys($mappa))->get();

        foreach ($cattedre as $cattedra) {
            $supplente = (int) $mappa[$cattedra->id];
            if (! in_array($supplente, $supplenti, true)) {
                throw ValidationException::withMessages(['assegnazioni' => 'Il docente scelto non è tra i supplenti indicati sulla sospensione.']);
            }
            if ($this->esiste($supplente, $cattedra)) {
                throw ValidationException::withMessages(['assegnazioni' => 'Il supplente ha già la cattedra '.$cattedra->etichettaAudit().': scegli un altro supplente.']);
            }
        }
        if ($cattedre->isEmpty()) {
            throw ValidationException::withMessages(['assegnazioni' => 'Scegli almeno una cattedra e il supplente a cui passarla.']);
        }

        // Per modello (non in blocco): ogni passaggio finisce nell'audit log.
        DB::transaction(fn () => $cattedre->each(fn (Cattedra $c) => $c->update(['docente_id' => (int) $mappa[$c->id], 'sospensione_id' => $sospensione->id])));

        return $cattedre->count();
    }

    /**
     * Riporta al titolare le cattedre passate ai supplenti.
     *
     * @return array{ripristinate: int, conflitti: list<string>} i conflitti restano al supplente (il titolare ha già quella cattedra)
     */
    public function ripristina(Sospensione $sospensione): array
    {
        $esito = ['ripristinate' => 0, 'conflitti' => []];

        DB::transaction(function () use ($sospensione, &$esito) {
            foreach ($sospensione->cattedreSostituite()->get() as $cattedra) {
                if ($this->esiste($sospensione->docente_id, $cattedra)) {
                    $esito['conflitti'][] = $cattedra->etichettaAudit();

                    continue;
                }
                $cattedra->update(['docente_id' => $sospensione->docente_id, 'sospensione_id' => null]);
                $esito['ripristinate']++;
            }
        });

        return $esito;
    }

    private function esiste(int $docenteId, Cattedra $cattedra): bool
    {
        return Cattedra::query()->where('docente_id', $docenteId)->where('classe_id', $cattedra->classe_id)
            ->where('disciplina_id', $cattedra->disciplina_id)->whereKeyNot($cattedra->id)->exists();
    }
}
