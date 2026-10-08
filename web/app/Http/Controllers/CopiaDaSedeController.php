<?php

namespace App\Http\Controllers;

use App\Models\Sede;
use App\Services\CopiaDaSede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CopiaDaSedeController extends Controller
{
    public function __invoke(Request $request, string $area, CopiaDaSede $servizio): RedirectResponse
    {
        abort_unless(isset(CopiaDaSede::AREE[$area]), 404);
        $origine = Sede::query()->findOrFail($request->validate(['sede_id' => ['required', 'integer']])['sede_id']);
        $ritorno = redirect()->route(CopiaDaSede::AREE[$area][2]);

        try {
            $esito = $servizio->copia($area, $origine);
        } catch (RuntimeException $e) {
            return $ritorno->withErrors(['copia' => $e->getMessage()]);
        }

        $messaggio = "Copiati dalla sede «{$origine->nome}»: {$esito['copiati']}.";
        if ($esito['note']) {
            $messaggio .= ' Non copiati: '.implode(' ', $esito['note']);
        }

        return $ritorno->with('successo', $messaggio);
    }
}
