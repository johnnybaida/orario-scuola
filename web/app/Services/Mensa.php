<?php

namespace App\Services;

use App\Models\AssistenzaPausa;
use App\Models\Aula;
use App\Models\Classe;
use App\Models\Slot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La mensa come pausa della scansione: le pause marcate «mensa», le classi che ci vanno (quelle che il giorno hanno ore dopo la
 * pausa: il rientro) e i docenti che la sorvegliano, memorizzati come assistenze alle pause con le classi sorvegliate
 * (nessuna classe indicata = tutte quelle in mensa quel giorno).
 */
class Mensa
{
    /** @return Collection<int, array> le pause marcate «mensa», per ordine dell'ora che le precede (vedi AssistenzaPause::pause()) */
    public function pause(): Collection
    {
        return app(AssistenzaPause::class)->pause()->filter(fn ($p) => $p['mensa']);
    }

    /** @return Collection<int, Classe> con slotAttivi */
    public function classi(): Collection
    {
        return Classe::query()->with('slotAttivi', 'quadroOrario')->orderBy('anno_corso')->orderBy('sezione')->get();
    }

    /**
     * Giorni in cui una classe è in mensa dopo l'ora $ordine: quelli in cui ha ore attive dopo la pausa (rientro).
     *
     * @return list<int>
     */
    public function giorni(Classe $classe, int $ordine): array
    {
        return $classe->slotAttivi->filter(fn (Slot $s) => $s->ordine > $ordine)->pluck('giorno')->unique()->sort()->values()->all();
    }

    /**
     * Docenti per classe e giorno di una pausa: [giorno => [classe_id => [docente_id]]], limitato alle classi in mensa quel giorno.
     *
     * @param  Collection<int, Classe>  $classi
     */
    public function assegnazioni(int $ordine, Collection $classi): array
    {
        $inMensa = [];
        foreach ($classi as $c) {
            foreach ($this->giorni($c, $ordine) as $g) {
                $inMensa[$g][$c->id] = true;
            }
        }

        $risultato = [];
        foreach (AssistenzaPausa::query()->where('ordine', $ordine)->with('classi')->get() as $a) {
            $sue = $a->classi->isEmpty() ? array_keys($inMensa[$a->giorno] ?? []) : $a->classi->pluck('id')->all();
            foreach ($sue as $classeId) {
                if (isset($inMensa[$a->giorno][$classeId])) {
                    $risultato[$a->giorno][$classeId][] = $a->docente_id;
                }
            }
        }

        return $risultato;
    }

    /**
     * Salva i docenti di una pausa: $celle = [giorno => [classe_id => [docente_id]]]. Per ogni giorno presente si ricostruiscono le
     * assistenze (un docente = una assistenza, con le sue classi); i giorni non presenti restano come sono.
     */
    public function salva(int $ordine, array $celle): void
    {
        DB::transaction(function () use ($ordine, $celle) {
            foreach ($celle as $giorno => $perClasse) {
                $perDocente = [];
                foreach ($perClasse as $classeId => $docenti) {
                    foreach (array_unique(array_filter($docenti)) as $docenteId) {
                        $perDocente[(int) $docenteId][] = (int) $classeId;
                    }
                }

                $esistenti = AssistenzaPausa::query()->where('ordine', $ordine)->where('giorno', $giorno)->get();
                // Per modello (non in blocco): così creazione, modifica ed eliminazione finiscono nell'audit log.
                $esistenti->reject(fn ($a) => isset($perDocente[$a->docente_id]))->each->delete();

                foreach ($perDocente as $docenteId => $classi) {
                    $assistenza = $esistenti->firstWhere('docente_id', $docenteId)
                        ?? AssistenzaPausa::query()->create(['docente_id' => $docenteId, 'giorno' => (int) $giorno, 'ordine' => $ordine]);
                    $assistenza->classi()->sync($classi);
                }
            }
        });
    }

    /**
     * Cosa manca per far funzionare la mensa, per la pagina Mensa (lista di controllo): [['ok' => bool, 'testo' => string, 'url' => ?string]].
     *
     * @return list<array{ok: bool, testo: string, url: ?string}>
     */
    public function controlli(): array
    {
        $pause = $this->pause();
        if ($pause->isEmpty()) {
            return [['ok' => false, 'testo' => 'Nessuna pausa è segnata come mensa: in Scansione oraria spunta «È la mensa» sulla pausa del pranzo.', 'url' => route('scansione.index')]];
        }

        $classi = $this->classi();
        $righe = [];
        foreach ($pause as $ordine => $pausa) {
            $righe[] = ['ok' => true, 'testo' => "Pausa mensa: {$pausa['etichetta']}", 'url' => route('scansione.index')];
            $righe[] = $pausa['aula']
                ? ['ok' => true, 'testo' => "Aula della mensa: {$pausa['aula']}", 'url' => route('scansione.index')]
                : ['ok' => false, 'testo' => 'La pausa non ha un\'aula: scegline una in Scansione oraria (tipo «Aula per la pausa»), così compare nei PDF.', 'url' => route('scansione.index')];
        }

        $inMensa = $classi->filter(fn (Classe $c) => $pause->keys()->contains(fn ($o) => $this->giorni($c, $o)));
        $righe[] = $inMensa->isEmpty()
            ? ['ok' => false, 'testo' => 'Nessuna classe è in mensa: una classe va in mensa nei giorni di rientro, cioè quando ha ore attive dopo la pausa (scheda della classe, Slot attivi).', 'url' => route('classi.index')]
            : ['ok' => true, 'testo' => $inMensa->count().' classi in mensa ('.$inMensa->map->nomeCompleto()->implode(', ').')', 'url' => route('classi.index')];

        $senzaOre = $inMensa->filter(fn (Classe $c) => (int) $c->quadroOrario->ore_mensa === 0 && $c->oreMensa() === 0);
        $righe[] = $senzaOre->isEmpty()
            ? ['ok' => true, 'testo' => 'I quadri orari delle classi in mensa indicano le ore di mensa', 'url' => route('quadri-orari.index')]
            : ['ok' => false, 'testo' => 'Il quadro orario di '.$senzaOre->map->nomeCompleto()->implode(', ').' non indica le ore di mensa (campo «Ore di mensa» del quadro).', 'url' => route('quadri-orari.index')];

        $slotSbagliati = $inMensa->filter(fn (Classe $c) => $c->slotAttivi->count() !== $c->quadroOrario->ore_totali - $c->oreMensa());
        $righe[] = $slotSbagliati->isEmpty()
            ? ['ok' => true, 'testo' => 'Gli slot attivi delle classi in mensa sono le ore delle discipline del quadro (senza la mensa)', 'url' => route('classi.index')]
            : ['ok' => false, 'testo' => 'Slot attivi da correggere (devono essere le ore di discipline, senza la mensa): '.$slotSbagliati->map(fn (Classe $c) => $c->nomeCompleto().' ne ha '.$c->slotAttivi->count().', ne servono '.($c->quadroOrario->ore_totali - $c->oreMensa()))->implode('; ').'.', 'url' => route('classi.index')];

        $mancanti = $this->celleSenzaDocente($pause, $classi);
        $righe[] = $mancanti === []
            ? ['ok' => true, 'testo' => 'Ogni classe in mensa ha almeno un docente ogni giorno', 'url' => null]
            : ['ok' => false, 'testo' => count($mancanti).(count($mancanti) === 1 ? ' cella (classe e giorno) senza docente' : ' celle (classe e giorno) senza docente').': compilale qui sotto.', 'url' => null];

        return $righe;
    }

    /** @return list<array{classe: Classe, giorno: int, ordine: int}> classi in mensa senza nessun docente quel giorno */
    public function celleSenzaDocente(?Collection $pause = null, ?Collection $classi = null): array
    {
        $pause ??= $this->pause();
        $classi ??= $this->classi();
        $mancanti = [];
        foreach ($pause as $ordine => $pausa) {
            $assegnate = $this->assegnazioni($ordine, $classi);
            foreach ($classi as $classe) {
                foreach ($this->giorni($classe, $ordine) as $giorno) {
                    if (empty($assegnate[$giorno][$classe->id])) {
                        $mancanti[] = ['classe' => $classe, 'giorno' => $giorno, 'ordine' => $ordine];
                    }
                }
            }
        }

        return $mancanti;
    }

    /**
     * Avvisi per il controllo prima di generare (non bloccanti): classi senza docente, aula troppo piccola, ore di mensa del quadro
     * diverse dai giorni in mensa.
     *
     * @return list<array{testo: string, url: string}>
     */
    public function avvisi(): array
    {
        $pause = $this->pause();
        if ($pause->isEmpty()) {
            return [];
        }
        $classi = $this->classi();
        $avvisi = [];

        $perClasse = collect($this->celleSenzaDocente($pause, $classi))->groupBy(fn ($m) => $m['classe']->id);
        foreach ($perClasse as $mancanti) {
            $giorni = $mancanti->pluck('giorno')->unique()->sort()->map(fn ($g) => mb_strtolower(Slot::GIORNI[$g] ?? $g))->implode(' e ');
            $avvisi[] = ['testo' => "Classe {$mancanti->first()['classe']->nomeCompleto()}: in mensa {$giorni} ma senza un docente che la sorvegli.", 'url' => route('mensa.index')];
        }

        foreach ($pause as $ordine => $pausa) {
            $aula = Aula::query()->where('nome', $pausa['aula'])->first();
            if (! $aula) {
                continue;
            }
            $conteggio = [];
            foreach ($classi as $classe) {
                foreach ($this->giorni($classe, $ordine) as $giorno) {
                    $conteggio[$giorno] = ($conteggio[$giorno] ?? 0) + 1;
                }
            }
            foreach ($conteggio as $giorno => $n) {
                if ($n > $aula->capienza) {
                    $avvisi[] = ['testo' => 'In mensa '.mb_strtolower(Slot::GIORNI[$giorno] ?? $giorno)." ci sono {$n} classi ma l'aula {$aula->nome} ne ospita {$aula->capienza} alla volta.", 'url' => route('aule.edit', $aula)];
                }
            }
        }

        foreach ($classi as $classe) {
            $ore = (int) $classe->quadroOrario->ore_mensa;
            $giorniInMensa = collect($pause->keys())->flatMap(fn ($o) => $this->giorni($classe, $o))->unique()->count();
            if ($ore > 0 && $ore !== $giorniInMensa) {
                $avvisi[] = ['testo' => "Classe {$classe->nomeCompleto()}: il quadro orario prevede {$ore}h di mensa ma la classe è in mensa {$giorniInMensa} giorni (quelli di rientro).", 'url' => route('classi.edit', $classe)];
            }
        }

        return $avvisi;
    }
}
