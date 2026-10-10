<?php

namespace App\Services\Substitution;

use App\Models\AuditLog;
use App\Models\AvvisoOrario;
use App\Models\CompresenzaSostegno;
use App\Models\Docente;
use App\Models\Lezione;
use App\Models\Orario;
use App\Services\Editor\EditorLezione;
use Illuminate\Support\Facades\DB;

/**
 * Sostituzione di un docente dentro un orario, senza rigenerare e senza toccare le cattedre: nelle lezioni (titolare e CLIL) il sostituto
 * sta in `lezioni.docente_sostituto_id` / `clil_sostituto_id`, nel sostegno la compresenza passa al sostituto e ricorda l'originale.
 * Di solito si lavora su una copia dell'orario (`duplica`): per il rientro basta ripubblicare l'originale.
 */
class SostituzioneOrario
{
    public function __construct(private EditorLezione $editor) {}

    /** Copia di un orario come nuova bozza (lezioni con le eventuali sostituzioni, compresenze di sostegno), legata all'originale. */
    public function duplica(Orario $orario, string $nome, int $utenteId): Orario
    {
        return DB::transaction(function () use ($orario, $nome, $utenteId) {
            $copia = Orario::query()->create([
                'periodo_id' => $orario->periodo_id,
                'versione' => (Orario::query()->where('periodo_id', $orario->periodo_id)->max('versione') ?? 0) + 1,
                'nome' => $nome ?: $orario->etichetta().' (copia)',
                'stato' => 'bozza',
                'seed' => $orario->seed,
                'punteggio' => $orario->punteggio,
                'creato_da' => $utenteId,
                'origine_id' => $orario->id,
            ]);

            $adesso = now();
            foreach ($orario->lezioni()->get()->chunk(200) as $gruppo) {
                Lezione::query()->insert($gruppo->map(fn (Lezione $l) => [
                    'orario_id' => $copia->id, 'cattedra_id' => $l->cattedra_id, 'slot_id' => $l->slot_id,
                    'durata_slot' => $l->durata_slot, 'aula_id' => $l->aula_id, 'bloccata' => $l->bloccata, 'con_clil' => $l->con_clil,
                    'docente_sostituto_id' => $l->docente_sostituto_id, 'clil_sostituto_id' => $l->clil_sostituto_id,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ])->all());
            }
            foreach ($orario->compresenzeSostegno()->get()->chunk(200) as $gruppo) {
                CompresenzaSostegno::query()->insert($gruppo->map(fn (CompresenzaSostegno $c) => [
                    'orario_id' => $copia->id, 'docente_id' => $c->docente_id, 'classe_id' => $c->classe_id,
                    'slot_id' => $c->slot_id, 'codice_anonimo' => $c->codice_anonimo, 'docente_originale_id' => $c->docente_originale_id,
                    'created_at' => $adesso, 'updated_at' => $adesso,
                ])->all());
            }

            AuditLog::query()->create([
                'user_id' => $utenteId, 'entita' => 'Orario', 'entita_id' => $copia->id, 'azione' => 'duplicazione',
                'dati_prima' => ['orario_origine' => $orario->id], 'dati_dopo' => $copia->only(['periodo_id', 'versione', 'stato']),
            ]);

            return $copia;
        });
    }

    /** I docenti che hanno ore nell'orario (come titolari, CLIL o sostegno): sono quelli che si possono sostituire. */
    public function docentiSostituibili(Orario $orario)
    {
        $ids = Lezione::query()->where('orario_id', $orario->id)->with('cattedra')->get()->flatMap(fn (Lezione $l) => $l->docentiIds())
            ->merge($orario->compresenzeSostegno()->pluck('docente_id'))->unique();

        return Docente::query()->whereIn('id', $ids)->orderBy('cognome')->orderBy('nome')->get();
    }

    /**
     * Passa a $supplente le ore di $assente: lezioni da titolare, da CLIL e/o di sostegno, eventualmente solo in alcuni giorni.
     * Le ore in cui il supplente non è libero (indisponibile, già in lezione o in sostegno altrove) restano all'assente e vengono segnalate:
     * si sistemano a mano dal pannello di ogni ora. Con `$supplente` uguale al titolare originale di una lezione la sostituzione si toglie.
     *
     * @param  array{titolare?: bool, clil?: bool, sostegno?: bool, giorni?: int[]}  $opzioni
     * @return array{sostituite: int, non_sostituite: list<string>}
     */
    public function sostituisci(Orario $orario, Docente $assente, Docente $supplente, array $opzioni, int $utenteId): array
    {
        $titolare = $opzioni['titolare'] ?? true;
        $clil = $opzioni['clil'] ?? true;
        $sostegno = $opzioni['sostegno'] ?? true;
        $giorni = array_map('intval', $opzioni['giorni'] ?? []);
        $supplente->loadMissing('indisponibilita');

        $fatte = 0;
        $non = [];

        DB::transaction(function () use ($orario, $assente, $supplente, $titolare, $clil, $sostegno, $giorni, $utenteId, &$fatte, &$non) {
            $lezioni = Lezione::query()->where('orario_id', $orario->id)->delDocente($assente->id)
                ->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'slot')->get()
                ->filter(fn (Lezione $l) => ! $giorni || in_array($l->slot->giorno, $giorni, true))->sortBy(fn (Lezione $l) => $l->slot->giorno * 100 + $l->slot->ordine);

            foreach ($lezioni as $l) {
                foreach (['titolare' => $titolare, 'clil' => $clil] as $ruolo => $attivo) {
                    $effettivo = $ruolo === 'clil' ? $l->docenteClilEffettivo() : $l->docenteEffettivo();
                    if (! $attivo || ! $effettivo || $effettivo->id !== $assente->id) {
                        continue;
                    }
                    $colonna = $ruolo === 'clil' ? 'clil_sostituto_id' : 'docente_sostituto_id';
                    $originale = $ruolo === 'clil' ? $l->cattedra->docente_clil_id : $l->cattedra->docente_id;
                    $motivi = $this->editor->conflittiPresenza($orario->id, $supplente, $l->slot_id, null, [$l->id]);
                    if ($motivi) {
                        $non[] = "{$l->cattedra->classe->nomeCompleto()}, {$l->slot->descrizione()} ({$l->cattedra->disciplina->nome}): {$supplente->nomeCompleto()} ".implode(' e ', $motivi).'.';

                        continue;
                    }
                    $l->update([$colonna => $supplente->id === $originale ? null : $supplente->id]);
                    $l->setRelation($colonna === 'clil_sostituto_id' ? 'clilSostituto' : 'docenteSostituto', $supplente);
                    $fatte++;
                }
            }

            if ($sostegno) {
                $compresenze = CompresenzaSostegno::query()->where('orario_id', $orario->id)->where('docente_id', $assente->id)->with('classe', 'slot')->get()
                    ->filter(fn (CompresenzaSostegno $c) => ! $giorni || in_array($c->slot->giorno, $giorni, true));
                foreach ($compresenze as $c) {
                    $motivi = $this->editor->conflittiPresenza($orario->id, $supplente, $c->slot_id, $c->classe_id);
                    if ($motivi) {
                        $non[] = "{$c->classe->nomeCompleto()}, {$c->slot->descrizione()} (sostegno): {$supplente->nomeCompleto()} ".implode(' e ', $motivi).'.';

                        continue;
                    }
                    $originale = $c->docente_originale_id ?: $c->docente_id;
                    $c->update(['docente_id' => $supplente->id, 'docente_originale_id' => $supplente->id === $originale ? null : $originale]);
                    $fatte++;
                }
            }

            foreach ($non as $messaggio) {
                AvvisoOrario::query()->create(['orario_id' => $orario->id, 'lezione_id' => null, 'tipo' => 'avviso', 'messaggio' => 'Sostituzione non applicata: '.$messaggio]);
            }
            AuditLog::query()->create([
                'user_id' => $utenteId, 'entita' => 'Orario', 'entita_id' => $orario->id, 'azione' => 'sostituzione',
                'dati_prima' => ['docente' => $assente->nomeCompleto()],
                'dati_dopo' => ['supplente' => $supplente->nomeCompleto(), 'ore_sostituite' => $fatte, 'ore_non_sostituite' => count($non)],
            ]);
        });

        return ['sostituite' => $fatte, 'non_sostituite' => $non];
    }
}
