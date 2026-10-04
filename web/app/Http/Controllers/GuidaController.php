<?php

namespace App\Http\Controllers;

use App\Support\Ruoli;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Serve la guida (docs/guida-utente.md) al pannello di aiuto, una sezione per ogni titolo "##", filtrata sul ruolo.
 *
 * Marcatori nel Markdown (i permessi sono i gate dell'applicazione, es. consulta, gestisci-anagrafica):
 *   <!-- sezione: consulta -->                  subito sotto un "##": l'intera sezione è solo per chi ha quel permesso
 *   <!-- permesso: gestisci-anagrafica --> … <!-- /permesso -->   un blocco visibile solo a chi ha quel permesso
 *   {ruolo}                                     diventa il nome del ruolo dell'utente
 */
class GuidaController extends Controller
{
    public function show(): JsonResponse
    {
        $utente = auth()->user();
        $parti = preg_split('/^## (.+)$/m', file_get_contents(config('app.radice').'/docs/guida-utente.md'), -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = fn (string $testo) => Str::markdown($this->perRuolo($testo), ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        // $parti[0] è l'introduzione sotto il titolo "#"; poi coppie [titolo, testo].
        $sezioni = [['id' => 'intro', 'titolo' => 'Introduzione', 'html' => $html(preg_replace('/^# .+$/m', '', $parti[0]))]];
        for ($i = 1; $i < count($parti); $i += 2) {
            if (preg_match('/<!-- sezione: ([\w-]+) -->/', $parti[$i + 1], $m) && ! Gate::allows($m[1])) {
                continue;
            }
            $sezioni[] = ['id' => Str::slug($parti[$i]), 'titolo' => $parti[$i], 'html' => $html($parti[$i + 1])];
        }

        return response()->json(['sezioni' => $sezioni, 'ruolo' => Ruoli::etichetta($utente->ruolo)]);
    }

    private function perRuolo(string $testo): string
    {
        $testo = preg_replace('/<!-- sezione: [\w-]+ -->\n?/', '', $testo);
        $testo = preg_replace_callback(
            '/<!-- permesso: ([\w-]+) -->\n?(.*?)<!-- \/permesso -->\n?/s',
            fn (array $m) => Gate::allows($m[1]) ? $m[2] : '',
            $testo,
        );

        return str_replace('{ruolo}', Ruoli::etichetta(auth()->user()->ruolo), $testo);
    }
}
