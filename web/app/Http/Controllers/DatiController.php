<?php

namespace App\Http\Controllers;

use App\Services\DatiScuola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Esporta e importa i dati dell'applicazione in uno ZIP (un JSON per tabella): solo l'amministratore. */
class DatiController extends Controller
{
    private const SESSIONE = 'dati_import';

    public function index(DatiScuola $dati): View
    {
        return view('dati.index', ['tabelle' => $this->elenco($dati)]);
    }

    public function esporta(Request $request, DatiScuola $dati): BinaryFileResponse|RedirectResponse
    {
        $scelte = $request->validate(['tabelle' => ['required', 'array', 'min:1'], 'tabelle.*' => ['string']])['tabelle'];

        try {
            $percorso = $dati->esporta($scelte);
        } catch (RuntimeException $e) {
            return back()->withErrors(['dati' => $e->getMessage()]);
        }

        return response()->download($percorso, 'orario-scuola-dati-'.now()->format('Ymd-His').'.zip')->deleteFileAfterSend();
    }

    /** Primo passo dell'import: carica lo ZIP e mostra cosa contiene, per scegliere cosa importare. */
    public function anteprima(Request $request, DatiScuola $dati): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:zip']]);
        $percorso = Storage::disk('local')->putFile('dati-import', $request->file('file'));

        try {
            $manifest = $dati->manifest(Storage::disk('local')->path($percorso));
        } catch (RuntimeException $e) {
            Storage::disk('local')->delete($percorso);

            return back()->withErrors(['dati' => $e->getMessage()]);
        }
        $this->scarta($request); // un eventuale caricamento precedente non confermato
        $request->session()->put(self::SESSIONE, $percorso);

        return view('dati.importa', [
            'manifest' => $manifest, 'conteggi' => $manifest['tabelle'],
            'tabelle' => array_intersect_key($this->elenco($dati), $manifest['tabelle']),
        ]);
    }

    public function importa(Request $request, DatiScuola $dati): RedirectResponse
    {
        $request->validate(['tabelle' => ['required', 'array', 'min:1'], 'tabelle.*' => ['string'], 'conferma' => ['accepted']]);
        $percorso = $request->session()->get(self::SESSIONE);
        if (! $percorso || ! Storage::disk('local')->exists($percorso)) {
            return redirect()->route('dati.index')->withErrors(['dati' => 'Il file caricato non c\'è più: caricalo di nuovo.']);
        }

        try {
            $esito = $dati->importa(Storage::disk('local')->path($percorso), $request->input('tabelle'));
        } catch (RuntimeException $e) {
            return redirect()->route('dati.index')->withErrors(['dati' => $e->getMessage()]);
        }
        $this->scarta($request);

        return redirect()->route('dati.index')->with('successo', 'Dati importati: '.count($esito['tabelle']).' tabelle, '.$esito['righe'].' righe.');
    }

    /** @return array<string, array{etichetta: string, righe: int}> */
    private function elenco(DatiScuola $dati): array
    {
        return collect($dati->tabelle())->mapWithKeys(fn ($t) => [$t => ['etichetta' => Str::headline($t), 'righe' => DB::table($t)->count()]])->all();
    }

    private function scarta(Request $request): void
    {
        if ($vecchio = $request->session()->pull(self::SESSIONE)) {
            Storage::disk('local')->delete($vecchio);
        }
    }
}
