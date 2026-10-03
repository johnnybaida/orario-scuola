<?php

namespace App\Http\Controllers;

use App\Http\Requests\UtenzaRequest;
use App\Models\Docente;
use App\Models\User;
use App\Support\Ruoli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UtenzaController extends Controller
{
    public function index(): View
    {
        return view('utenze.index', ['utenze' => User::query()->with('docente')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('utenze.create', $this->opzioni());
    }

    public function store(UtenzaRequest $request): RedirectResponse
    {
        User::query()->create($this->dati($request));

        return redirect()->route('utenze.index')->with('successo', 'Utenza creata.');
    }

    public function edit(User $utenza): View
    {
        return view('utenze.edit', ['utenza' => $utenza, ...$this->opzioni()]);
    }

    public function update(UtenzaRequest $request, User $utenza): RedirectResponse
    {
        // Evita di restare senza amministratori togliendo il ruolo a se stessi.
        if ($utenza->is($request->user()) && $request->input('ruolo') !== Ruoli::AMMINISTRATORE) {
            throw ValidationException::withMessages(['ruolo' => 'Non puoi togliere a te stesso il ruolo di amministratore.']);
        }

        $utenza->update($this->dati($request));

        return redirect()->route('utenze.index')->with('successo', 'Utenza aggiornata.');
    }

    public function destroy(User $utenza): RedirectResponse
    {
        abort_if($utenza->is(request()->user()), 422, 'Non puoi eliminare la tua utenza.');

        $utenza->delete();

        return redirect()->route('utenze.index')->with('successo', 'Utenza eliminata.');
    }

    private function dati(UtenzaRequest $request): array
    {
        $dati = $request->validated();
        if (empty($dati['password'])) {
            unset($dati['password']);
        }
        // Il collegamento al docente ha senso solo per il ruolo "docente".
        if ($dati['ruolo'] !== Ruoli::DOCENTE) {
            $dati['docente_id'] = null;
        }

        return $dati;
    }

    private function opzioni(): array
    {
        return ['ruoli' => Ruoli::tutti(), 'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get()];
    }
}
