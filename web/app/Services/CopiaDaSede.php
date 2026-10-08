<?php

namespace App\Services;

use App\Models\Aula;
use App\Models\AuditLog;
use App\Models\Disciplina;
use App\Models\Impostazioni;
use App\Models\QuadroOrario;
use App\Models\QuadroOrarioRiga;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\Vincolo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Copia in una sede nuova (o vuota) una parte della configurazione di un'altra sede, per non ripartire da zero. Si può
 * copiare un'area solo se nella sede corrente è vuota. Docenti, classi e cattedre non si copiano: dipendono dalle persone.
 */
class CopiaDaSede
{
    /** area => [etichetta, modello, route dell'elenco] */
    public const AREE = [
        'scansione' => ['Scansione oraria e impostazioni (conteggio del sostegno)', Slot::class, 'scansione.index'],
        'discipline' => ['Discipline', Disciplina::class, 'discipline.index'],
        'quadri' => ['Quadri orari', QuadroOrario::class, 'quadri-orari.index'],
        'aule' => ['Aule', Aula::class, 'aule.index'],
        'vincoli' => ['Vincoli (solo quelli per tutte le classi e i docenti)', Vincolo::class, 'vincoli.index'],
    ];

    public function __construct(private readonly SedeCorrente $corrente)
    {
    }

    /** L'area è vuota nella sede in cui si lavora. */
    public function vuota(string $area): bool
    {
        return self::AREE[$area][1]::query()->doesntExist();
    }

    /** @return Collection<int, array{sede: Sede, elementi: int}> le altre sedi che hanno qualcosa in quest'area */
    public function origini(string $area): Collection
    {
        $modello = self::AREE[$area][1];

        return Sede::query()->where('id', '!=', $this->corrente->id())->orderBy('nome')->get()
            ->map(fn (Sede $s) => ['sede' => $s, 'elementi' => $modello::query()->withoutGlobalScopes()->where('sede_id', $s->id)->count()])
            ->filter(fn ($o) => $o['elementi'] > 0)->values();
    }

    /**
     * @return array{copiati: int, saltati: int, note: list<string>}
     *
     * @throws RuntimeException se l'area non è vuota o mancano prerequisiti
     */
    public function copia(string $area, Sede $origine): array
    {
        if (! $this->vuota($area)) {
            throw new RuntimeException('Quest\'area non è vuota: la copia è possibile solo su un\'area vuota.');
        }
        if ($origine->id === $this->corrente->id()) {
            throw new RuntimeException('Scegli un\'altra sede.');
        }

        $esito = AuditLog::senza(fn () => DB::transaction(fn () => $this->{'copia'.ucfirst($area)}($origine)));
        AuditLog::registra('Sede', $this->corrente->id(), 'duplicazione', null,
            ['area' => self::AREE[$area][0], 'da_sede' => $origine->nome, 'copiati' => $esito['copiati'], 'saltati' => $esito['saltati']], "Copia da «{$origine->nome}»: ".self::AREE[$area][0]);

        return $esito;
    }

    private function nuova($modello, array $cambia = []): void
    {
        $copia = $modello->replicate();
        $copia->sede_id = $this->corrente->id();
        $copia->forceFill($cambia)->save();
    }

    private function copiaScansione(Sede $origine): array
    {
        $slot = Slot::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->orderBy('giorno')->orderBy('ordine')->get();
        $slot->each(fn (Slot $s) => $this->nuova($s));

        $impostazioni = Impostazioni::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->first();
        if ($impostazioni) {
            Impostazioni::correnti()->update($impostazioni->only(['conteggio_sostegno']));
        }

        return ['copiati' => $slot->count(), 'saltati' => 0, 'note' => []];
    }

    private function copiaDiscipline(Sede $origine): array
    {
        $discipline = Disciplina::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->orderBy('id')->get();
        $nuovi = [];
        foreach ($discipline as $d) {
            $copia = $d->replicate();
            $copia->sede_id = $this->corrente->id();
            $copia->padre_id = null;
            $copia->save();
            $nuovi[$d->id] = $copia;
        }
        foreach ($discipline->whereNotNull('padre_id') as $d) {
            $nuovi[$d->id]->update(['padre_id' => ($nuovi[$d->padre_id] ?? null)?->id]);
        }

        return ['copiati' => $discipline->count(), 'saltati' => 0, 'note' => []];
    }

    private function copiaQuadri(Sede $origine): array
    {
        $quadri = QuadroOrario::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->get();
        $righe = QuadroOrarioRiga::query()->withoutGlobalScopes()->whereIn('quadro_orario_id', $quadri->pluck('id'))->get();
        $codici = Disciplina::query()->withoutGlobalScopes()->whereIn('id', $righe->pluck('disciplina_id')->unique())->pluck('codice', 'id');
        $qui = Disciplina::query()->pluck('id', 'codice');
        if ($mancanti = $codici->unique()->diff($qui->keys())) {
            if ($mancanti->isNotEmpty()) {
                throw new RuntimeException('Mancano in questa sede le discipline: '.$mancanti->implode(', ').'. Copia o crea prima le discipline.');
            }
        }

        foreach ($quadri as $q) {
            $copia = $q->replicate();
            $copia->sede_id = $this->corrente->id();
            $copia->save();
            foreach ($righe->where('quadro_orario_id', $q->id) as $r) {
                $copia->righe()->create(['disciplina_id' => $qui[$codici[$r->disciplina_id]], 'ore_settimanali' => $r->ore_settimanali]);
            }
        }

        return ['copiati' => $quadri->count(), 'saltati' => 0, 'note' => []];
    }

    private function copiaAule(Sede $origine): array
    {
        $aule = Aula::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->get();
        $aule->each(fn (Aula $a) => $this->nuova($a));

        return ['copiati' => $aule->count(), 'saltati' => 0, 'note' => []];
    }

    private function copiaVincoli(Sede $origine): array
    {
        $copiati = 0;
        $note = [];
        $disciplineOrigine = Disciplina::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->pluck('codice', 'id');
        $disciplineQui = Disciplina::query()->pluck('id', 'codice');
        $slotOrigine = Slot::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->get()->keyBy('id');
        $slotQui = Slot::query()->get()->mapWithKeys(fn (Slot $s) => [$s->giorno.'-'.$s->ordine => $s->id]);

        $vincoli = Vincolo::query()->withoutGlobalScopes()->where('sede_id', $origine->id)->get();
        foreach ($vincoli as $v) {
            $parametri = $v->parametri ?? [];
            $etichetta = $v->tipo;
            if ($v->ambito_livello !== 'globale') {
                $note[] = "{$etichetta}: salto, vale per classi o docenti di un'altra sede.";

                continue;
            }
            if (! empty($parametri['disciplina_id'])) {
                $id = $disciplineQui[$disciplineOrigine[$parametri['disciplina_id']] ?? null] ?? null;
                if (! $id) {
                    $note[] = "{$etichetta}: salto, la disciplina non c'è in questa sede.";

                    continue;
                }
                $parametri['disciplina_id'] = $id;
            }
            if (! empty($parametri['slot_ids'])) {
                $nuovi = collect($parametri['slot_ids'])->map(fn ($id) => isset($slotOrigine[$id]) ? ($slotQui[$slotOrigine[$id]->giorno.'-'.$slotOrigine[$id]->ordine] ?? null) : null);
                if ($nuovi->contains(null)) {
                    $note[] = "{$etichetta}: salto, la scansione oraria di questa sede non ha le stesse ore.";

                    continue;
                }
                $parametri['slot_ids'] = $nuovi->values()->all();
            }
            $this->nuova($v, ['parametri' => $parametri, 'profilo_vincoli_id' => null]);
            $copiati++;
        }

        return ['copiati' => $copiati, 'saltati' => $vincoli->count() - $copiati, 'note' => $note];
    }
}
