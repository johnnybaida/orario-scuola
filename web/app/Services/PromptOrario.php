<?php

namespace App\Services;

use App\Constraints\Catalogo;
use App\Enums\TipoAula;
use App\Models\Aula;
use App\Models\Cattedra;
use App\Models\Classe;
use App\Models\Disciplina;
use App\Models\Docente;
use App\Models\Impostazioni;
use App\Models\Laboratorio;
use App\Models\Slot;
use App\Models\Vincolo;
use Illuminate\Support\Collection;

/**
 * Testo da dare a un'AI perché simuli la generazione dell'orario: riepiloga i dati della sede corrente (scansione, aule,
 * discipline, classi con quadro orario, docenti con cattedre, sostegno, laboratori, vincoli) e le regole del generatore.
 */
class PromptOrario
{
    public function testo(): string
    {
        $slot = Slot::query()->orderBy('giorno')->orderBy('ordine')->get();
        $sezioni = [
            $this->introduzione(),
            $this->regole(),
            $this->glossario(),
            $this->scansione(),
            $this->aule(),
            $this->discipline(),
            $this->classi($slot),
            $this->docenti($slot),
            $this->sostegno(),
            $this->mensa(),
            $this->laboratori(),
            $this->vincoli($slot),
            $this->richiesta(),
        ];

        return implode("\n\n", array_filter($sezioni))."\n";
    }

    private function intro(string $titolo, array $righe): string
    {
        return "## {$titolo}\n".implode("\n", $righe ?: ['(nessuno)']);
    }

    /** «LUN 1ª–6ª; MER 3ª, 5ª»: le ore raggruppate per giorno (intervalli di ore consecutive). */
    private function oreCompatte(Collection $slot): string
    {
        return $slot->sortBy(fn (Slot $s) => $s->giorno * 100 + $s->ordine)->groupBy('giorno')->map(function ($ore, $giorno) {
            $gruppi = [];
            foreach ($ore->pluck('ordine')->sort()->values() as $o) {
                $ultimo = count($gruppi) - 1;
                if ($ultimo >= 0 && $gruppi[$ultimo][1] === $o - 1) {
                    $gruppi[$ultimo][1] = $o;
                } else {
                    $gruppi[] = [$o, $o];
                }
            }

            return (Slot::GIORNI_BREVI[$giorno] ?? $giorno).' '.collect($gruppi)->map(fn ($g) => $g[0] === $g[1] ? "{$g[0]}ª" : "{$g[0]}ª–{$g[1]}ª")->implode(', ');
        })->implode('; ');
    }

    private function nomeSlot(Slot $s): string
    {
        return (Slot::GIORNI_BREVI[$s->giorno] ?? $s->giorno).' '.$s->ordine.'ª';
    }

    private function introduzione(): string
    {
        return "# Generazione dell'orario settimanale di una scuola secondaria di I grado\n\n"
            ."Sei un esperto di orari scolastici. Con i dati e le regole qui sotto costruisci l'orario settimanale di tutte le classi: "
            ."per ogni classe indica, per ogni giorno e ora, la disciplina, il docente e l'aula. Le persone non sono censite: gli alunni compaiono solo come numero.";
    }

    private function regole(): string
    {
        return "## Regole sempre obbligatorie\n"
            ."- Una classe ha al massimo una lezione per ora (salvo compresenze dichiarate).\n"
            ."- Un docente è in un solo posto per ora e non può avere lezione nelle ore in cui è indisponibile.\n"
            ."- Ogni classe riempie esattamente tutte le sue ore attive; ogni cattedra ha esattamente le sue ore settimanali.\n"
            ."- Un'aula ospita al massimo «capienza» classi nella stessa ora; le discipline che richiedono un tipo di aula vanno in un'aula di quel tipo.\n"
            ."- Le discipline «senza ora» (per esempio la mensa) contano nel quadro orario ma non sono lezioni da collocare.\n"
            ."- Le lezioni bloccate non si spostano. I vincoli preferenziali sono desideri: rispettane il più possibile (peso più alto = più importante).";
    }

    private function glossario(): string
    {
        return "## Glossario\n"
            ."- **Ora / slot**: una lezione della scansione settimanale (giorno + numero d'ora, es. «LUN 3ª»). **Ore attive** di una classe: gli slot che la classe usa davvero; vanno riempiti tutti, esattamente una volta.\n"
            ."- **Rientro pomeridiano**: giorno in cui una classe a tempo prolungato ha lezione anche nelle ore del pomeriggio (dalla 7ª).\n"
            ."- **Quadro orario**: le ore settimanali di ogni disciplina per una classe. **Cattedra**: un docente che insegna una disciplina in una classe per un certo numero di ore settimanali.\n"
            ."- **Compresenza**: due docenti insieme nella stessa lezione (stesso slot, stessa classe): nessuno dei due è «in due posti».\n"
            ."- **CLIL**: docente (spesso madrelingua) presente insieme al titolare solo per alcune ore di una cattedra; in quelle ore non può essere altrove e rispetta le sue indisponibilità.\n"
            ."- **Senza ora / mensa**: la mensa è una pausa (non un'ora di lezione): conta come «ore di mensa» nel quadro orario e come ore di sorveglianza nel monte ore dei docenti, ma non va collocata in un'ora; una disciplina «senza ora» (metodo precedente) si comporta allo stesso modo.\n"
            ."- **Sostegno**: docente assegnato a una classe che affianca i colleghi; «per_alunno» = l'ora copre un solo alunno, «per_classe» = copre tutti; «docente unico» = lo stesso docente per tutte le ore di quel fabbisogno. Gli alunni sono identificati solo da un codice anonimo.\n"
            ."- **DADA**: didattica per ambienti: le classi non hanno aula fissa, sono gli alunni a spostarsi nell'aula della disciplina; il tipo di aula dice per quali discipline va bene (un'aula può essere condivisa da più discipline).\n"
            ."- **Capienza**: quante classi possono usare l'aula nella stessa ora. **Piano**: piano dell'edificio di aule e classi (serve ai vincoli sugli spostamenti).\n"
            ."- **Ore dovute / monte ore**: ore settimanali che il docente deve fare (18 = cattedra intera): cattedre, sostegno, ore CLIL e assistenza alle pause (60 minuti = 1 ora).\n"
            ."- **Assistenza alle pause**: sorveglianza di una pausa (es. mensa) in un certo giorno; **laboratorio pomeridiano**: attività fissa fuori dal monte ore che occupa docenti e aula in certe ore.\n"
            .'- **Buca**: ora libera di un docente tra due sue lezioni dello stesso giorno. **Blocco**: ore della stessa disciplina consecutive nello stesso giorno. **Severità** di un vincolo: «rigido» = obbligatorio, «preferenziale» = desiderio con un peso da 1 a 100.';
    }

    private function scansione(): string
    {
        $ore = app(ScansioneOraria::class)->ore();
        $righe = $ore->map(function (Slot $s, $ordine) use ($ore) {
            $pausa = $s->ricreazione_minuti ? ", poi {$s->nomePausa()} di {$s->ricreazione_minuti}'" : '';
            $prima = $s->pausa_prima_minuti ? " (preceduta da {$s->nomePausaPrima()} di {$s->pausa_prima_minuti}')" : '';

            return "- {$ordine}ª ora: ".substr($s->inizio, 0, 5).'–'.substr($s->fine, 0, 5).$prima.$pausa;
        })->all();
        $giorni = Slot::query()->distinct()->orderBy('giorno')->pluck('giorno')->map(fn ($g) => Slot::GIORNI[$g] ?? $g)->implode(', ');

        return $this->intro("Scansione oraria (uguale per tutti i giorni: {$giorni})", $righe)
            ."\nOgni classe usa solo le proprie ore attive (vedi sotto); le pause non sono ore di lezione.";
    }

    private function aule(): string
    {
        $righe = Aula::query()->orderBy('nome')->get()->map(fn (Aula $a) => "- {$a->nome}: tipo «".TipoAula::etichettaDi($a->tipo)."», capienza {$a->capienza} (classi nella stessa ora)"
            .($a->piano !== null ? ", piano {$a->piano}" : ''))->all();

        return $this->intro('Aule', $righe);
    }

    private function discipline(): string
    {
        $righe = Disciplina::query()->with('padre')->orderBy('nome')->get()->map(function (Disciplina $d) {
            $aule = collect($d->tipiAmmessi())->map(fn ($t) => TipoAula::etichettaDi($t))->implode(' oppure ');

            return "- {$d->codice} – {$d->nome}".($aule ? "; richiede aula: {$aule}" : '').($d->senza_slot ? '; NON occupa un\'ora di lezione'.($d->pausa_dopo_ora !== null ? " (si svolge nella pausa dopo la {$d->pausa_dopo_ora}ª ora)" : '') : '')
                .($d->padre ? "; sotto-disciplina di {$d->padre->nome}" : '');
        })->all();

        return $this->intro('Discipline', $righe);
    }

    private function classi(Collection $slot): string
    {
        $classi = Classe::query()->with('aulaBase', 'quadroOrario.righe.disciplina', 'slotAttivi')->orderBy('anno_corso')->orderBy('sezione')->get();
        $righe = $classi->map(function (Classe $c) {
            $quadro = $c->quadroOrario->righe->sortBy('disciplina.nome')->map(fn ($r) => "{$r->disciplina->codice} {$r->ore_settimanali}h")->implode(', ');
            $ore = $c->slotAttivi->sortBy(fn ($s) => $s->giorno * 100 + $s->ordine);
            $perGiorno = $ore->groupBy('giorno')->map(fn ($g, $giorno) => (Slot::GIORNI_BREVI[$giorno] ?? $giorno).' ore '.$g->pluck('ordine')->implode(','))->implode('; ');

            return "- Classe {$c->nomeCompleto()} ({$c->tempo_scuola}, {$c->n_alunni} alunni".($c->aulaBase ? ", aula base {$c->aulaBase->nome}" : ', senza aula base').($c->piano !== null ? ", piano {$c->piano}" : '').")\n"
                ."  Quadro orario «{$c->quadroOrario->nome}» ({$c->quadroOrario->ore_totali}h): {$quadro}\n"
                ."  Ore attive ({$ore->count()}): {$perGiorno}";
        })->all();

        return $this->intro('Classi e quadri orari', $righe);
    }

    private function docenti(Collection $slot): string
    {
        $righe = Docente::query()->with('indisponibilita', 'sospensioni', 'assistenzePausa', 'cattedre.classe', 'cattedre.disciplina', 'cattedre.docenteClil')
            ->orderBy('cognome')->orderBy('nome')->get()->map(function (Docente $d) {
                $cattedre = $d->cattedre->sortBy(fn ($c) => $c->classe->nomeCompleto().$c->disciplina->nome)->map(function (Cattedra $c) {
                    return "{$c->disciplina->nome} in {$c->classe->nomeCompleto()} ({$c->ore}h"
                        .($c->compresenza ? ', in compresenza' : '')
                        .($c->docenteClil && $c->ore_clil ? ", con {$c->docenteClil->nomeCompleto()} (CLIL) in compresenza per {$c->ore_clil}h" : '').')';
                })->implode('; ');
                $indisp = $this->oreCompatte($d->indisponibilita);
                $sosp = $d->sospensioni->map(fn ($s) => $s->etichettaMotivo().' '.$s->periodo().($s->esclude_da_orario ? ' (escluso dall\'orario)' : ''))->implode('; ');
                $assist = app(AssistenzaPause::class)->elenco($d->loadMissing('assistenzePausa'));

                return "- {$d->nomeCompleto()} – posto {$d->tipo_posto}, regime {$d->regime}, {$d->ore_dovute} ore dovute\n"
                    .'  Cattedre: '.($cattedre ?: 'nessuna')."\n"
                    .($indisp ? "  Indisponibile: {$indisp}\n" : '')
                    .($sosp ? "  Sospensioni: {$sosp}\n" : '')
                    .($assist ? '  Assistenza alle pause: '.implode(', ', $assist)."\n" : '');
            })->map(fn (string $riga) => rtrim($riga))->all();

        return $this->intro('Docenti e cattedre', $righe);
    }

    private function sostegno(): string
    {
        $classi = Classe::query()->whereHas('fabbisogniSostegno')->with('fabbisogniSostegno', 'assegnazioniSostegno.docente')->orderBy('anno_corso')->orderBy('sezione')->get();
        if ($classi->isEmpty()) {
            return '';
        }
        $righe = $classi->map(function (Classe $c) {
            $fab = $c->fabbisogniSostegno->map(fn ($f) => "{$f->codice_anonimo} {$f->ore_settimanali}h".($f->docente_unico ? ' (un solo docente)' : ''))->implode(', ');
            $doc = $c->assegnazioniSostegno->map(fn ($a) => "{$a->docente->nomeCompleto()} {$a->ore}h")->implode(', ');

            return "- {$c->nomeCompleto()}: fabbisogni {$fab}; docenti assegnati {$doc}; conteggio ".$c->conteggioSostegnoEffettivo();
        })->all();

        return $this->intro('Sostegno (le ore sono compresenze con gli altri docenti della classe)', $righe)
            ."\nConteggio «per_alunno»: ogni ora di un docente copre un solo alunno; «per_classe»: copre tutti gli alunni della classe. Predefinito della sede: ".Impostazioni::correnti()->conteggio_sostegno.'.';
    }

    private function mensa(): string
    {
        $mensa = app(Mensa::class);
        $pause = $mensa->pause();
        if ($pause->isEmpty()) {
            return '';
        }
        $classi = $mensa->classi();
        $docenti = \App\Models\Docente::query()->pluck('cognome', 'id');
        $righe = [];
        foreach ($pause as $ordine => $pausa) {
            $assegnate = $mensa->assegnazioni($ordine, $classi);
            $righe[] = "- {$pausa['etichetta']}".($pausa['aula'] ? ", aula {$pausa['aula']}" : '').' (non è una lezione: nessuna ora da collocare)';
            foreach ($classi as $classe) {
                $giorni = $mensa->giorni($classe, $ordine);
                if (! $giorni) {
                    continue;
                }
                $per = collect($giorni)->map(fn ($g) => (Slot::GIORNI_BREVI[$g] ?? $g).': '.(collect($assegnate[$g][$classe->id] ?? [])->map(fn ($d) => $docenti[$d] ?? '?')->implode(', ') ?: 'nessun docente'))->implode('; ');
                $righe[] = "  - Classe {$classe->nomeCompleto()} in mensa: {$per}";
            }
        }

        return $this->intro('Mensa (pausa pranzo: i docenti la sorvegliano, le ore contano nel loro monte ore)', $righe);
    }

    private function laboratori(): string
    {
        $righe = Laboratorio::query()->attivi()->with('aula', 'docenti', 'classi', 'slot')->orderBy('nome')->get()->map(fn (Laboratorio $l) => "- {$l->nome}: ".$l->quando()
            .($l->aula ? ", aula {$l->aula->nome}" : '').', docenti '.$l->docenti->map->nomeCompleto()->implode(', ')
            .($l->classi->isNotEmpty() ? ', classi '.$l->classi->map->nomeCompleto()->implode(', ') : ''))->all();

        return $righe ? $this->intro('Laboratori pomeridiani (fuori dal monte ore: occupano docenti e aule in quelle ore, non vanno spostati)', $righe) : '';
    }

    private function vincoli(Collection $slot): string
    {
        $vincoli = Vincolo::attivi()->get();
        $righe = $vincoli->map(function (Vincolo $v) {
            $ambito = $v->ambito_livello === 'globale' ? 'tutte le classi/docenti' : $v->ambito_livello.' '.$this->nomi($v->ambito_livello, $v->ambito_ids ?? []);
            $sev = $v->severita === 'rigido' ? 'OBBLIGATORIO' : "preferenziale (peso {$v->peso}/100)";

            return "- [{$sev}] ".Catalogo::istanza($v->tipo)->etichetta()." – valido per: {$ambito} – ".$this->regolaVincolo($v->tipo, $this->interi($v->parametri ?? []), $v->severita === 'rigido')
                .($v->nota ? " [nota: {$v->nota}]" : '');
        })->all();

        return $this->intro('Vincoli configurati', $righe);
    }

    private function interi(array $parametri): array
    {
        return array_map(fn ($p) => is_array($p) ? $this->interi($p) : (is_string($p) && preg_match('/^-?\d+$/', $p) ? (int) $p : $p), $parametri);
    }

    /** Il vincolo spiegato a parole (i nomi dei parametri del programma non dicono niente a chi legge). */
    private function regolaVincolo(string $tipo, array $p, bool $rigido): string
    {
        $nomiDiscipline = \App\Constraints\DisciplineVincolo::nomi($p, '');
        $disciplina = $nomiDiscipline !== '' ? (count(\App\Constraints\DisciplineVincolo::ids($p)) > 1 ? "ciascuna tra [{$nomiDiscipline}]" : $nomiDiscipline) : null;
        $ore = fn (array $ids) => $this->oreCompatte(Slot::query()->whereIn('id', $ids)->get());
        $giorni = fn (array $g) => collect($g)->map(fn ($n) => Slot::GIORNI[$n] ?? $n)->implode(', ');

        return match ($tipo) {
            'D1_BLOCCO_MIN_CONSECUTIVO' => 'in almeno '.($p['n_blocchi_min'] ?? 1).' giorno/i della settimana '.($disciplina ? "{$disciplina} deve avere" : 'le lezioni del docente devono avere')
                ." un blocco di almeno {$p['min_consecutive']} ore consecutive (conta i giorni con un blocco, non i blocchi)",
            'D12_BLOCCO_MAX_CONSECUTIVO' => ($disciplina ? "{$disciplina}: " : 'le lezioni del docente: ')."al massimo {$p['max_consecutive']} ore consecutive nello stesso giorno (le pause non interrompono la fila)",
            'S5_DISTRIBUZIONE_SOSTEGNO' => 'sostegno: '.collect([
                ($p['max_insieme'] ?? '') !== '' ? "al massimo {$p['max_insieme']} docente/i di sostegno insieme nella stessa classe e ora" : null,
                ($p['tolleranza_giorno'] ?? '') !== '' ? "ore di sostegno distribuite nella settimana (per classe, al massimo la media sui giorni in cui un docente può esserci + {$p['tolleranza_giorno']} ora/e al giorno)" : null,
            ])->filter()->implode('; '),
            'T11_ORE_IN_FASCIA' => "ogni docente scelto deve fare almeno {$p['min_ore']} ora/e (lezioni o sostegno, in qualunque classe) in queste ore: ".$ore($p['slot_ids'] ?? []),
            'D3_MAX_ORE_GIORNO' => "{$disciplina} al massimo {$p['max']} ora/e al giorno per classe",
            'D6_FASCIA_ORARIA' => ($p['tipo'] ?? '') === 'vietata'
                ? "{$disciplina} NON può essere collocata in queste ore: ".$ore($p['slot_ids'] ?? [])
                : "{$disciplina} può essere collocata SOLO in queste ore: ".$ore($p['slot_ids'] ?? [])." (ogni lezione fuori da esse è una violazione)",
            'T2_GIORNO_LIBERO' => "ogni docente deve avere almeno {$p['n_giorni']} giorno/i della settimana senza lezioni"
                .(! empty($p['preferenze']) ? ' (giorni preferiti per il libero: '.$giorni($p['preferenze']).')' : ''),
            'T3_MAX_ORE_BUCHE' => 'ore buca dei docenti: '.collect([
                isset($p['max_per_giorno']) && $p['max_per_giorno'] !== '' ? "al massimo {$p['max_per_giorno']} al giorno" : null,
                isset($p['max_per_settimana']) && $p['max_per_settimana'] !== '' ? "al massimo {$p['max_per_settimana']} a settimana" : null,
            ])->filter()->implode(' e '),
            'C5_SPOSTAMENTI_PIANO' => 'tra due ore consecutive di una classe il piano dell\'aula (o della classe) non dovrebbe cambiare di più di '.($p['soglia'] ?? 0).' piani (0 = nessun cambio di piano); riguarda gli spostamenti degli alunni',
            default => $this->parametri($p),
        };
    }

    private function nomi(string $livello, array $ids): string
    {
        $modello = match ($livello) {
            'classe' => Classe::class, 'docente' => Docente::class, 'disciplina' => Disciplina::class, 'aula' => Aula::class, default => null,
        };

        return $modello ? $modello::query()->whereIn('id', $ids)->get()->map(fn ($m) => method_exists($m, 'nomeCompleto') ? $m->nomeCompleto() : $m->nome)->implode(', ') : '';
    }

    /** Parametri di un vincolo con gli id sostituiti da nomi. */
    private function parametri(array $parametri): string
    {
        $out = [];
        foreach ($parametri as $chiave => $valore) {
            if ($chiave === 'disciplina_ids' || $chiave === 'disciplina_id') {
                $out[] = \App\Constraints\DisciplineVincolo::ids($parametri) ? 'discipline: '.\App\Constraints\DisciplineVincolo::nomi($parametri) : 'tutte le lezioni (nessuna disciplina in particolare)';
            } elseif ($chiave === 'slot_ids') {
                $out[] = 'ore: '.$this->oreCompatte(Slot::query()->whereIn('id', $valore)->get());
            } elseif (is_array($valore)) {
                $out[] = "{$chiave}: ".implode(', ', $valore);
            } else {
                $out[] = "{$chiave}: ".(is_bool($valore) ? ($valore ? 'sì' : 'no') : $valore);
            }
        }

        return implode('; ', $out) ?: 'senza parametri';
    }

    private function richiesta(): string
    {
        return "## Cosa devi produrre\n"
            ."1. Un orario per ogni classe: una tabella con i giorni in colonna e le ore in riga; in ogni cella disciplina, docente e aula.\n"
            ."2. Un riepilogo dei vincoli preferenziali che non sei riuscito a rispettare, con il motivo.\n"
            ."3. Se i dati sono incompatibili (per esempio un docente con più ore di quelle disponibili) dimmi quali e perché, invece di inventare soluzioni.\n"
            .'Prima di rispondere verifica da solo che nessun docente né aula sia doppio nella stessa ora e che ogni cattedra abbia le sue ore.';
    }
}
