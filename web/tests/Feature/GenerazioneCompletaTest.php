<?php

namespace Tests\Feature;

use App\Models\Aula;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Generazione;
use App\Models\Laboratorio;
use App\Models\Lezione;
use App\Models\QuadroOrario;
use App\Models\Sede;
use App\Models\Slot;
use App\Models\User;
use App\Models\Vincolo;
use App\Services\Editor\ControlloOrario;
use App\Services\Validation\PreValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generazione con il solver reale su una scuola piccola che mette insieme tutte le funzioni recenti: mensa «senza ora» con due
 * docenti, docente CLIL su una parte delle ore, aule DADA proprie e condivise, laboratorio pomeridiano, sostegno,
 * indisponibilità e i vincoli D1, D3, D6, T3 (salvati come li salva il form: id e slot come stringhe). Il risultato si
 * controlla con verifiche indipendenti dal solver; il test è sensibile (vedi CLAUDE.md: verifica per mutazione).
 */
class GenerazioneCompletaTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classeA;

    private Classe $classeB;

    private array $d = [];

    private array $disc = [];

    private function slot(int $giorno, int $ordine): Slot
    {
        return Slot::query()->where('giorno', $giorno)->where('ordine', $ordine)->firstOrFail();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $sede = Sede::factory()->create();

        // Scansione: lunedì e martedì, ore 1-4 al mattino; il lunedì anche la 7ª (rientro).
        foreach ([1, 2] as $giorno) {
            foreach ([1, 2, 3, 4] as $ordine) {
                Slot::query()->create(['giorno' => $giorno, 'ordine' => $ordine, 'inizio' => sprintf('%02d:00:00', 7 + $ordine), 'fine' => sprintf('%02d:50:00', 7 + $ordine), 'intervallo_dopo' => false]);
            }
        }
        Slot::query()->create(['giorno' => 1, 'ordine' => 7, 'inizio' => '14:00:00', 'fine' => '14:50:00', 'intervallo_dopo' => false]);

        // Aule: una per disciplina DADA, una condivisa Geografia/Scienze, palestra, aula del laboratorio.
        foreach ([['Aula Geo', 'dada_geo'], ['Aula Sci', 'dada_sci'], ['Aula Geo-Sci', 'dada_geo__sci'], ['Palestra', 'palestra'], ['Sala lab', 'laboratorio']] as [$nome, $tipo]) {
            Aula::factory()->create(['sede_id' => $sede->id, 'nome' => $nome, 'tipo' => $tipo, 'capienza' => 1]);
        }

        $nuova = fn (string $codice, array $extra = []) => Disciplina::factory()->create(['codice' => $codice, 'nome' => $codice] + $extra);
        $this->disc = [
            'ITA' => $nuova('ITA'), 'MAT' => $nuova('MAT'), 'ING' => $nuova('ING'),
            'GEO' => $nuova('GEO', ['tipo_aula_richiesto' => 'dada_geo', 'tipi_aula_extra' => ['dada_geo__sci']]),
            'SCI' => $nuova('SCI', ['tipo_aula_richiesto' => 'dada_sci', 'tipi_aula_extra' => ['dada_geo__sci']]),
            'MOT' => $nuova('MOT', ['tipo_aula_richiesto' => 'palestra']),
            'MEN' => $nuova('MEN', ['senza_slot' => true]),
        ];

        foreach (['ITA', 'MAT', 'ING', 'GEO', 'SCI', 'MOT', 'M1', 'M2', 'CLIL', 'SOST'] as $sigla) {
            $this->d[$sigla] = Docente::factory()->create(['cognome' => $sigla, 'nome' => 'X', 'tipo_posto' => $sigla === 'SOST' ? 'sostegno' : 'comune']);
        }
        $this->d['MAT']->indisponibilita()->attach($this->slot(2, 4)->id);
        $this->d['ITA']->indisponibilita()->attach($this->slot(1, 1)->id);
        // La docente CLIL è disponibile solo il lunedì e il martedì alla 4ª ora: le sue due ore in compresenza devono finire lì.
        $this->d['CLIL']->indisponibilita()->attach(Slot::query()->where('ordine', '!=', 4)->pluck('id'));

        // Quadri: A = 8 ore; B = 9 ore di lezione + 1 di mensa (tempo prolungato con rientro del lunedì).
        $base = ['ITA' => 3, 'MAT' => 2, 'GEO' => 1, 'SCI' => 1, 'ING' => 1];
        $quadroA = QuadroOrario::factory()->create(['ore_totali' => 8]);
        $quadroB = QuadroOrario::factory()->create(['ore_totali' => 10]);
        foreach ($base as $codice => $ore) {
            $quadroA->righe()->create(['disciplina_id' => $this->disc[$codice]->id, 'ore_settimanali' => $ore]);
            $quadroB->righe()->create(['disciplina_id' => $this->disc[$codice]->id, 'ore_settimanali' => $ore]);
        }
        $quadroB->righe()->create(['disciplina_id' => $this->disc['MOT']->id, 'ore_settimanali' => 1]);
        $quadroB->righe()->create(['disciplina_id' => $this->disc['MEN']->id, 'ore_settimanali' => 1]);

        $this->classeA = Classe::factory()->create(['sede_id' => $sede->id, 'anno_corso' => 1, 'sezione' => 'A', 'quadro_orario_id' => $quadroA->id, 'tempo_scuola' => 'normale']);
        $this->classeB = Classe::factory()->create(['sede_id' => $sede->id, 'anno_corso' => 1, 'sezione' => 'B', 'quadro_orario_id' => $quadroB->id, 'tempo_scuola' => 'prolungato']);
        $mattina = Slot::query()->where('ordine', '<=', 4)->pluck('id');
        $this->classeA->slotAttivi()->sync($mattina);
        $this->classeB->slotAttivi()->sync($mattina->push($this->slot(1, 7)->id));

        // Cattedre: la docente CLIL è in compresenza su 1 ora di Geografia in A e su 1 ora di Scienze in B.
        foreach ([$this->classeA, $this->classeB] as $classe) {
            foreach (['ITA' => 3, 'MAT' => 2, 'GEO' => 1, 'SCI' => 1, 'ING' => 1] as $codice => $ore) {
                $clil = ($codice === 'GEO' && $classe->is($this->classeA)) || ($codice === 'SCI' && $classe->is($this->classeB));
                $classe->cattedre()->create(['docente_id' => $this->d[$codice]->id, 'disciplina_id' => $this->disc[$codice]->id, 'ore' => $ore,
                    'docente_clil_id' => $clil ? $this->d['CLIL']->id : null, 'ore_clil' => $clil ? 1 : 0]);
            }
        }
        $this->classeB->cattedre()->create(['docente_id' => $this->d['MOT']->id, 'disciplina_id' => $this->disc['MOT']->id, 'ore' => 1]);
        $this->classeB->cattedre()->create(['docente_id' => $this->d['M1']->id, 'disciplina_id' => $this->disc['MEN']->id, 'ore' => 1]);
        $this->classeB->cattedre()->create(['docente_id' => $this->d['M2']->id, 'disciplina_id' => $this->disc['MEN']->id, 'ore' => 1, 'compresenza' => true]);

        // Sostegno nella classe A.
        $this->classeA->fabbisogniSostegno()->create(['codice_anonimo' => '1A-S1', 'ore_settimanali' => 2]);
        $this->classeA->assegnazioniSostegno()->create(['docente_id' => $this->d['SOST']->id, 'ore' => 2]);

        // Laboratorio del pomeriggio: il docente di Motoria è occupato il lunedì alla 7ª ora, nell'aula del laboratorio.
        $lab = Laboratorio::factory()->create(['sede_id' => $sede->id, 'nome' => 'Lab', 'aula_id' => Aula::query()->where('nome', 'Sala lab')->value('id'), 'attivo' => true]);
        $lab->docenti()->sync([$this->d['MOT']->id]);
        $lab->slot()->sync([$this->slot(1, 7)->id]);

        // Vincoli, come li salva il form: id e numeri come stringhe.
        $vincolo = fn (array $dati) => Vincolo::factory()->create($dati + ['ambito_livello' => 'globale', 'ambito_ids' => null, 'attivo' => true]);
        $vincolo(['tipo' => 'D6_FASCIA_ORARIA', 'severita' => 'rigido', 'peso' => null,
            'parametri' => ['disciplina_id' => (string) $this->disc['MAT']->id, 'tipo' => 'vietata', 'slot_ids' => [(string) $this->slot(1, 1)->id]]]);
        $vincolo(['tipo' => 'D3_MAX_ORE_GIORNO', 'severita' => 'rigido', 'peso' => null, 'parametri' => ['disciplina_id' => (string) $this->disc['ITA']->id, 'max' => '2']]);
        $vincolo(['tipo' => 'D1_BLOCCO_MIN_CONSECUTIVO', 'severita' => 'rigido', 'peso' => null, 'ambito_livello' => 'classe', 'ambito_ids' => [(string) $this->classeA->id],
            'parametri' => ['disciplina_id' => (string) $this->disc['ITA']->id, 'min_consecutive' => '2', 'n_blocchi_min' => '1']]);
        // Matematica mai due ore di fila (D12), come la salva il form
        $vincolo(['tipo' => 'D12_BLOCCO_MAX_CONSECUTIVO', 'severita' => 'rigido', 'peso' => null, 'parametri' => ['disciplina_id' => (string) $this->disc['MAT']->id, 'max_consecutive' => '1']]);
        $vincolo(['tipo' => 'T3_MAX_ORE_BUCHE', 'severita' => 'preferenziale', 'peso' => 50, 'parametri' => ['max_per_giorno' => 1]]);
    }

    public function test_la_pre_validazione_non_trova_problemi(): void
    {
        $this->assertSame([], (new PreValidator)->esegui());
    }

    public function test_la_generazione_rispetta_mensa_clil_aule_laboratorio_sostegno_e_vincoli(): void
    {
        $referente = $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
        $referente->post('/generazioni', ['time_limit_s' => 60, 'seed' => 11]);

        $generazione = Generazione::query()->latest('id')->firstOrFail();
        $this->assertSame('completata', $generazione->stato, json_encode($generazione->diagnostica));
        $orario = $generazione->orario;
        $lezioni = Lezione::query()->where('orario_id', $orario->id)->with('cattedra.classe', 'cattedra.disciplina', 'cattedra.docente', 'cattedra.docenteClil', 'slot', 'aula')->get();

        // Il controllo dell'applicazione non trova errori (docente in due posti, indisponibile, aule, ore diverse dal quadro...).
        $errori = collect(app(ControlloOrario::class)->problemi($orario))->where('gravita', 'errore')->pluck('testo')->all();
        $this->assertSame([], $errori);

        // Lezioni: 8 + 9 (la mensa non è una lezione) e ogni slot attivo di ogni classe coperto una volta sola.
        $this->assertCount(17, $lezioni);
        $this->assertSame(0, $lezioni->filter(fn ($l) => $l->cattedra->disciplina->codice === 'MEN')->count());
        foreach ([$this->classeA, $this->classeB] as $classe) {
            $suoi = $lezioni->filter(fn ($l) => $l->cattedra->classe_id === $classe->id);
            $this->assertEqualsCanonicalizing($classe->slotAttivi()->pluck('slot.id')->all(), $suoi->pluck('slot_id')->all(), "slot di {$classe->nomeCompleto()}");
        }

        // CLIL: una lezione in A (Geografia) e una in B (Scienze), negli unici slot in cui la docente è disponibile.
        $clil = $lezioni->where('con_clil', true);
        $this->assertCount(2, $clil);
        $this->assertEqualsCanonicalizing(['GEO-A', 'SCI-B'], $clil->map(fn ($l) => $l->cattedra->disciplina->codice.'-'.$l->cattedra->classe->sezione)->values()->all());
        $this->assertEqualsCanonicalizing([$this->slot(1, 4)->id, $this->slot(2, 4)->id], $clil->pluck('slot_id')->all());
        foreach ($lezioni as $l) {
            $presenti = $lezioni->filter(fn ($altra) => $altra->slot_id === $l->slot_id && in_array($this->d['CLIL']->id, $altra->docentiIds()));
            $this->assertLessThanOrEqual(1, $presenti->count());
        }

        // Nessun docente in due classi nello stesso slot né in un'ora di indisponibilità.
        foreach ($lezioni->groupBy(fn ($l) => $l->cattedra->docente_id.'-'.$l->slot_id) as $gruppo) {
            $this->assertCount(1, $gruppo);
        }
        $this->assertFalse($lezioni->contains(fn ($l) => $l->cattedra->docente_id === $this->d['MAT']->id && $l->slot_id === $this->slot(2, 4)->id));
        $this->assertFalse($lezioni->contains(fn ($l) => $l->cattedra->docente_id === $this->d['ITA']->id && $l->slot_id === $this->slot(1, 1)->id));

        // Laboratorio: il docente di Motoria non ha lezione il lunedì alla 7ª; in quell'ora la classe B ha comunque una lezione.
        $this->assertFalse($lezioni->contains(fn ($l) => $l->cattedra->docente_id === $this->d['MOT']->id && $l->slot_id === $this->slot(1, 7)->id));
        $this->assertTrue($lezioni->contains(fn ($l) => $l->cattedra->classe_id === $this->classeB->id && $l->slot_id === $this->slot(1, 7)->id));

        // Aule: Geografia e Scienze nelle proprie o in quella condivisa, Motoria in palestra, mai due classi nella stessa aula e ora.
        foreach ($lezioni as $l) {
            $codice = $l->cattedra->disciplina->codice;
            if (in_array($codice, ['GEO', 'SCI', 'MOT'], true)) {
                $this->assertNotNull($l->aula, "{$codice} senza aula");
                $this->assertTrue($l->cattedra->disciplina->accettaTipo($l->aula->tipo), "{$codice} in {$l->aula->nome}");
            }
        }
        foreach ($lezioni->filter(fn ($l) => $l->aula_id)->groupBy(fn ($l) => $l->aula_id.'-'.$l->slot_id) as $gruppo) {
            $this->assertCount(1, $gruppo);
        }

        // Vincoli rigidi: Matematica mai il lunedì alla 1ª; Italiano al massimo 2 ore al giorno per classe, e in A in un blocco di 2 consecutive.
        $this->assertFalse($lezioni->contains(fn ($l) => $l->cattedra->disciplina->codice === 'MAT' && $l->slot_id === $this->slot(1, 1)->id));
        // D12: le due ore di Matematica di ogni classe non sono mai di fila nello stesso giorno
        foreach ([$this->classeA, $this->classeB] as $classe) {
            $mat = $lezioni->filter(fn ($l) => $l->cattedra->classe_id === $classe->id && $l->cattedra->disciplina->codice === 'MAT');
            foreach ($mat->groupBy(fn ($l) => $l->slot->giorno) as $giorno) {
                $ordini = $giorno->pluck('slot.ordine')->sort()->values();
                $this->assertTrue($ordini->count() < 2 || $ordini->last() - $ordini->first() > 1, 'Matematica di fila in '.$classe->nomeCompleto());
            }
        }
        foreach ([$this->classeA, $this->classeB] as $classe) {
            $ita = $lezioni->filter(fn ($l) => $l->cattedra->classe_id === $classe->id && $l->cattedra->disciplina->codice === 'ITA');
            foreach ($ita->groupBy(fn ($l) => $l->slot->giorno) as $giorno) {
                $this->assertLessThanOrEqual(2, $giorno->count());
            }
        }
        $itaA = $lezioni->filter(fn ($l) => $l->cattedra->classe_id === $this->classeA->id && $l->cattedra->disciplina->codice === 'ITA');
        $this->assertTrue($itaA->groupBy(fn ($l) => $l->slot->giorno)->contains(fn ($g) => $g->pluck('slot.ordine')->sort()->values()->pipe(fn ($o) => $o->count() >= 2 && $o->last() - $o->first() === $o->count() - 1)));

        // Sostegno: 2 compresenze per la classe A, nei slot in cui la classe ha lezione (il docente di sostegno non ha altre lezioni).
        $compresenze = $orario->compresenzeSostegno()->get();
        $this->assertCount(2, $compresenze);
        $this->assertTrue($compresenze->every(fn ($c) => $c->docente_id === $this->d['SOST']->id && $c->classe_id === $this->classeA->id));
        $this->assertCount(2, $compresenze->pluck('slot_id')->unique());
    }

    public function test_il_laboratorio_occupa_il_docente_e_senza_di_lui_la_generazione_cambia(): void
    {
        // Nella 7ª ora del lunedì solo Motoria può fare lezione alla classe B (gli altri docenti sono indisponibili).
        foreach (['ITA', 'MAT', 'GEO', 'SCI', 'ING'] as $sigla) {
            $this->d[$sigla]->indisponibilita()->syncWithoutDetaching([$this->slot(1, 7)->id]);
        }
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));

        // Con il laboratorio il docente di Motoria è occupato in quell'ora: nessuna soluzione.
        $this->post('/generazioni', ['time_limit_s' => 60, 'seed' => 3]);
        $this->assertSame('infattibile', Generazione::query()->latest('id')->first()->stato);

        // Senza laboratorio Motoria fa lezione alla classe B il lunedì alla 7ª.
        Laboratorio::query()->update(['attivo' => false]);
        $this->post('/generazioni', ['time_limit_s' => 60, 'seed' => 3]);
        $generazione = Generazione::query()->latest('id')->first();
        $this->assertSame('completata', $generazione->stato);
        $lezione = $generazione->orario->lezioni()->where('slot_id', $this->slot(1, 7)->id)->with('cattedra.disciplina')->first();
        $this->assertSame('MOT', $lezione->cattedra->disciplina->codice);
    }

    public function test_la_stessa_generazione_con_lo_stesso_seed_e_riproducibile(): void
    {
        $this->actingAs(User::factory()->create(['ruolo' => 'referente_orario']));
        $this->post('/generazioni', ['time_limit_s' => 60, 'seed' => 5]);
        $primo = Generazione::query()->latest('id')->first()->orario->lezioni()->orderBy('cattedra_id')->orderBy('slot_id')->get(['cattedra_id', 'slot_id', 'con_clil'])->toArray();
        $this->post('/generazioni', ['time_limit_s' => 60, 'seed' => 5]);
        $secondo = Generazione::query()->latest('id')->first()->orario->lezioni()->orderBy('cattedra_id')->orderBy('slot_id')->get(['cattedra_id', 'slot_id', 'con_clil'])->toArray();

        $this->assertSame($primo, $secondo);
    }
}
