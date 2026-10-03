<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/** Serve la guida (docs/guida-utente.md) al pannello di aiuto: una sezione per ogni titolo "##". */
class GuidaController extends Controller
{
    public function show(): JsonResponse
    {
        $parti = preg_split('/^## (.+)$/m', file_get_contents(base_path('docs/guida-utente.md')), -1, PREG_SPLIT_DELIM_CAPTURE);
        $markdown = fn (string $testo) => Str::markdown($testo, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        // $parti[0] è l'introduzione sotto il titolo "#"; poi coppie [titolo, testo].
        $sezioni = [['id' => 'intro', 'titolo' => 'Introduzione', 'html' => $markdown(preg_replace('/^# .+$/m', '', $parti[0]))]];
        for ($i = 1; $i < count($parti); $i += 2) {
            $sezioni[] = ['id' => Str::slug($parti[$i]), 'titolo' => $parti[$i], 'html' => $markdown($parti[$i + 1])];
        }

        return response()->json(['sezioni' => $sezioni]);
    }
}
