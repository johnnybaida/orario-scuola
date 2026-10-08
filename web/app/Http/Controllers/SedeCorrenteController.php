<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SedeCorrenteController extends Controller
{
    /** Cambia la sede in cui si lavora (ricordata per l'utente) e torna alla pagina iniziale della sezione. */
    public function __invoke(Request $request): RedirectResponse
    {
        $sede = Sede::query()->findOrFail($request->validate(['sede_id' => ['required', 'integer']])['sede_id']);
        $request->session()->put('sede_id', $sede->id);
        \Illuminate\Support\Facades\DB::table('users')->where('id', $request->user()->id)->update(['ultima_sede_id' => $sede->id]); // senza eventi: non è una modifica dell'utenza

        return redirect()->route('dashboard')->with('successo', "Stai lavorando nella sede «{$sede->nome}».");
    }
}
