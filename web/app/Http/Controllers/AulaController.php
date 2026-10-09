<?php

namespace App\Http\Controllers;

use App\Enums\TipoAula;
use App\Http\Requests\AulaRequest;
use App\Models\Aula;
use App\Models\Disciplina;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AulaController extends Controller
{
    public function index(): View
    {
        return view('aule.index', [
            'aule' => Aula::query()->orderBy('nome')->get(),
            'usataDa' => Disciplina::query()->get()->flatMap(fn (Disciplina $d) => array_map(fn ($t) => [$t, $d->nome], $d->tipiAmmessi()))
                ->groupBy(0)->map(fn ($coppie) => $coppie->pluck(1)->implode(', ')),
        ]);
    }

    public function create(): View
    {
        return view('aule.create', [
            'tipiSuggeriti' => $this->tipiSuggeriti(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function store(AulaRequest $request): RedirectResponse
    {
        Aula::query()->create($this->dati($request));

        return redirect()->route('aule.index')->with('successo', 'Aula creata.');
    }

    public function edit(Aula $aula): View
    {
        return view('aule.edit', [
            'aula' => $aula,
            'tipiSuggeriti' => $this->tipiSuggeriti(),
            'discipline' => Disciplina::query()->orderBy('nome')->get(),
        ]);
    }

    public function update(AulaRequest $request, Aula $aula): RedirectResponse
    {
        $aula->update($this->dati($request, $aula));

        return redirect()->route('aule.index')->with('successo', 'Aula aggiornata.');
    }

    public function destroy(Aula $aula): RedirectResponse
    {
        $aula->delete();

        return redirect()->route('aule.index')->with('successo', 'Aula eliminata.');
    }

    /** Tipi base più quelli già in uso, esclusi i DADA: per quelli la UI propone «DADA» con le discipline da spuntare. */
    private function tipiSuggeriti(): array
    {
        $usati = Aula::query()->distinct()->orderBy('tipo')->pluck('tipo')->all();

        return array_values(array_filter(array_unique([...TipoAula::comuni(), ...$usati]), fn (string $t) => ! TipoAula::eDada($t)));
    }

    /**
     * Con tipo «dada» il tipo vero si ricava dalle discipline spuntate (vedi TipoAula::dadaPerGruppo) e viene **aggiunto**
     * ai tipi che quelle discipline ammettono: ciascuna può così avere la propria aula e anche una condivisa. Se l'aula
     * cambia tipo, il vecchio tipo si toglie alle discipline che lo ammettevano, a meno che un'altra aula lo abbia ancora.
     */
    private function dati(AulaRequest $request, ?Aula $aula = null): array
    {
        $dati = $request->safe()->except('dada_discipline');
        if ($dati['tipo'] === 'dada') {
            $scelte = Disciplina::query()->whereIn('id', $request->input('dada_discipline', []))->get();
            $dati['tipo'] = TipoAula::dadaPerGruppo($scelte);
            $scelte->each(fn (Disciplina $d) => $d->aggiungiTipo($dati['tipo']));
        }

        $vecchio = $aula?->tipo;
        if ($vecchio && $vecchio !== $dati['tipo'] && TipoAula::eDada($vecchio) && ! Aula::query()->where('tipo', $vecchio)->whereKeyNot($aula->id)->exists()) {
            Disciplina::query()->get()->each(fn (Disciplina $d) => $d->togliTipo($vecchio));
        }

        return $dati;
    }
}
