<?php

namespace App\Http\Controllers;

use App\Models\Docente;
use App\Services\Mensa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pagina Mensa: chi sorveglia la mensa, classe per classe e giorno per giorno. */
class MensaController extends Controller
{
    public function index(Mensa $mensa): View
    {
        $pause = $mensa->pause();
        $classi = $mensa->classi();

        return view('mensa.index', [
            'controlli' => $mensa->controlli(),
            'pause' => $pause,
            'classi' => $classi,
            'giorniDi' => fn ($classe, $ordine) => $mensa->giorni($classe, $ordine),
            'assegnazioni' => $pause->mapWithKeys(fn ($p, $ordine) => [$ordine => $mensa->assegnazioni($ordine, $classi)])->all(),
            'docenti' => Docente::query()->orderBy('cognome')->orderBy('nome')->get(),
            'puoModificare' => auth()->user()->can('gestisci-anagrafica'),
        ]);
    }

    public function update(Request $request, Mensa $mensa): RedirectResponse
    {
        $request->validate([
            'celle' => ['nullable', 'array'],
            'celle.*.*.*' => ['array'],
            'celle.*.*.*.*' => ['nullable', app(\App\Services\SedeCorrente::class)->esiste('docenti')],
        ]);

        $pause = $mensa->pause();
        foreach ($request->input('celle', []) as $ordine => $perGiorno) {
            if ($pause->has((int) $ordine)) {   // solo le pause segnate come mensa
                $mensa->salva((int) $ordine, $perGiorno);
            }
        }

        return redirect()->route('mensa.index')->with('successo', 'Mensa aggiornata.');
    }
}
